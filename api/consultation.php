<?php
$dbname = "pgsql:host=localhost;dbname=Hospital";
$user = 'postgres';
$password = '123';

try {
    $db = new PDO($dbname, $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["Internal Server Error"]);
    exit;
}
if (preg_match('/\/api\/consultation\.php\/([0-9a-f\-]+)$/i', $_SERVER['REQUEST_URI'], $matches) && $_SERVER['REQUEST_METHOD'] === 'GET'){
    if (true){
        $data = json_decode(file_get_contents('php://input'), true);
        $headers = getallheaders();
        $token = "";
        if (isset($headers['Authorization'])) {
            $token = explode(' ', $headers['Authorization'])[1];
        }
        $massage = "SELECT id FROM doctor WHERE token = :token";
        $stmt = $db->prepare($massage);
        $stmt->bindValue(':token', $token);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            http_response_code(401);
            echo json_encode(['Unauthorized']);
            exit;
        }
    }
    $consultationid=$matches[1];
    $massage = "SELECT id,createtime,inspectionid,specialityid FROM consultation WHERE id = :id";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':id', $consultationid);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }
    $consultation = $stmt ->fetch(PDO::FETCH_ASSOC);
    $massage = "SELECT id,name,createTime FROM speciality WHERE id = :id";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':id', $consultation['specialityid']);
    $stmt->execute();
    $speciality = $stmt ->fetch(PDO::FETCH_ASSOC);
    $massage = "SELECT id,author,createTime,modifyTime,content,parentid FROM comment WHERE consultationid = :id";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':id', $consultation['id']);
    $stmt->execute();
    $comments = $stmt ->fetchAll(PDO::FETCH_ASSOC);
    $empty =[];
    foreach($comments as $comment){
        $massage = "SELECT name FROM doctor WHERE id = :id";
        $stmt = $db->prepare($massage);
        $stmt->bindValue(':id', $comment['author']);
        $stmt->execute();
        $author = $stmt ->fetch(PDO::FETCH_ASSOC);
        $Commresponse=[
            "id" => $comment["id"],
            "createTime" => $comment["createtime"],
            "modifiedDate"=>$comment["modifytime"],
            "content"=>$comment["content"],
            "authorId"=>$comment["author"],
            "author"=> $author['name'],
            "parentId"=> $comment["parentid"]
        ];
        $empty[]=$Commresponse;
    }
    $Cresponse = [
        "id" => $consultation["id"],
        "createTime" => $consultation["createtime"],
        "inspectionId" => $consultation["inspectionid"],
        "speciality" => $speciality,
        "comments" => $empty,
    ];
    http_response_code(200);
    echo json_encode(["Success" => $Cresponse]);
}else if (strpos($_SERVER['REQUEST_URI'], '/api/consultation.php')!== false && $_SERVER['REQUEST_METHOD'] === 'GET') { 
    if (true){ 
        $data = json_decode(file_get_contents('php://input'), true); 
        $headers = getallheaders(); 
        $token = ""; 
        if (isset($headers['Authorization'])) { 
            $token = explode(' ', $headers['Authorization'])[1]; 
        } 
        $massage = "SELECT id FROM doctor WHERE token = :token"; 
        $stmt = $db->prepare($massage); 
        $stmt->bindValue(':token', $token); 
        $stmt->execute(); 
        if ($stmt->rowCount() === 0) { 
            http_response_code(401); 
            echo json_encode(['Unauthorized']); 
            exit; 
        } 
    } 
    $grouped = filter_var($_GET['grouped'], FILTER_VALIDATE_BOOLEAN) ?? false; 
    $page = $_GET['page'] ?? 1; 
    $size = $_GET['size'] ?? 10; 
    $flag=true; 
    if($_GET['icdRoots']!= '' && $_GET['icdRoots']!=null){ 
        $roots = explode(',', $_GET['icdRoots']); 
    }else {
        $roots = [null]; $flag=false;
    } 
 
    $massage = "SELECT * FROM diagnos" ; 
    $stmt = $db->prepare($massage); 
    $stmt->execute(); 
    $diagnoses=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $responsediagnos = []; 
    if ($flag){ 
        foreach($diagnoses as $diagnos){ 
 
            $idparent = null; 
            $idcurent = $diagnos['icddiagnosisid']; 
            $massage = "SELECT id_parent FROM icd10 WHERE (id = :id)"; 
            $stmt = $db->prepare($massage); 
            $stmt->bindValue(':id', $idcurent); 
            $stmt->execute(); 
            $idparent=$stmt->fetch(PDO::FETCH_ASSOC); 
 
            while($idparent['id_parent']!=NULL){ 
                $idcurent= $idparent['id_parent']; 
                $massage = "SELECT id_parent FROM icd10 WHERE (id = :id)"; 
                $stmt = $db->prepare($massage); 
                $stmt->bindValue(':id', $idcurent); 
                $stmt->execute(); 
                $idparent=$stmt->fetch(PDO::FETCH_ASSOC); 
            } 
            foreach($roots as $root){ 
                if ($idcurent == $root){ 
                    $responsediagnos[] = $diagnos['id']; 
                    break; 
                } 
            } 
        } 
        if (empty($responsediagnos)) { 
            $responsediagnos[]=null; 
        } 
    } 
    $inspections = []; 
    $stmt = $db->prepare('SELECT inspectionId FROM consultation'); 
    $stmt->execute(); 
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC); 
    function getInspectionChain($inspectionId, $db, $responsediagnos) { 
 
        $massage = "SELECT i.* FROM inspection i";  
        if (!empty($responsediagnos)) { 
            $massage .= "JOIN diagnos d ON i.id = d.inspectionid "; 
        } 
 
        $massage .= "WHERE i.id = :inspectionId ";  
        if (!empty($responsediagnos)) { 
            $massage .= "AND d.id IN (:icdIds) "; 
        } 
 
        $massage .= "GROUP BY i.id";  
        if (!empty($responsediagnos)) { 
            $massage = str_replace(':icdIds', implode(', ', $responsediagnos), $massage); 
        } 
 
        $stmt = $db->prepare($massage); 
        $stmt->bindValue(':inspectionId', $inspectionId); 
        $stmt->execute(); 
 
        if ($stmt->rowCount() > 0) { 
            $inspection = $stmt->fetch(PDO::FETCH_ASSOC); 
 
            $massage = "SELECT name FROM doctor WHERE id = :id"; 
            $stmt = $db->prepare($massage); 
            $stmt->bindValue(':id', $inspection['author']); 
            $stmt->execute(); 
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC); 
 
            $massage = "SELECT name FROM patient WHERE id = :id"; 
            $stmt = $db->prepare($massage); 
            $stmt->bindValue(':id', $inspection['patientid']); 
            $stmt->execute(); 
            $patient = $stmt->fetch(PDO::FETCH_ASSOC); 
 
            $massage = "SELECT id,icdDiagnosisID,description,type,createtime FROM diagnos WHERE inspectionid = :id"; 
            $stmt =$db->prepare($massage); 
            $stmt->bindValue(':id', $inspectionId); 
            $stmt->execute(); 
            $diagnoses = $stmt->fetchAll(PDO::FETCH_ASSOC); 
 
            $Ndiagnoses=[]; 
            foreach ($diagnoses as $diagnos) 
            { 
                $massage = "SELECT mkb_code,mkb_name FROM icd10 WHERE id = :id"; 
                $stmt = $db->prepare($massage); 
                $stmt->bindValue(':id', $diagnos['icddiagnosisid']); 
                $stmt->execute(); 
                $icd = $stmt ->fetch(PDO::FETCH_ASSOC); 
                $Ndiagnos = [ 
                    "id" => $diagnos["id"], 
                    "createTime" => $diagnos["createtime"], 
                    "code" => $icd["mkb_code"], 
                    "name" => $icd["mkb_name"], 
                    "description" => $diagnos["description"], 
                    "type" => $diagnos["type"], 
                ]; 
                $Ndiagnoses[] = $Ndiagnos; 
            } 
 
            $massage = "SELECT i.* FROM inspection i"; 
 
            if (!empty($responsediagnos)) { 
                $massage .= "JOIN diagnos d ON i.id = d.inspectionid "; 
            } 
 
            $massage .= "WHERE previousinspectionid = :id "; 
 
            if (!empty($responsediagnos)) { 
                $massage .= "AND d.id IN (:icdIds) OR d.id IS NULL "; 
            } 
 
            $massage .= "GROUP BY i.id"; 
            $massage = str_replace(':icdIds', implode(', ', $responsediagnos), $massage); 
 
            $stmt = $db->prepare($massage); 
            $stmt->bindValue(':id', $inspectionId); 
            $stmt->execute(); 
            $nests=$stmt->fetchAll(PDO::FETCH_ASSOC); 
            $inspectionData = [ 
                "id" => $inspection["id"], 
                "createTime" => $inspection["createtime"], 
                "previousId" => $inspection["previousinspectionid"], 
                "date" => $inspection["date"], 
                "conclusion" => $inspection["conclusion"], 
                "doctorId" => $inspection["author"], 
                "doctor" => $doctor['name'], 
                "patientId" => $inspection["patientid"], 
                "patient" => $patient['name'], 
                "diagnosis" => $Ndiagnoses, 
                "hasChain" => !is_null($inspection["previousinspectionid"]), 
                "hasNested" => $stmt->rowCount() > 0 
            ]; 
 
            $inspections[] = $inspectionData; 
             
            foreach($nests as $nest) 
            { 
                $inspections = array_merge($inspections, getInspectionChain( $nest['id'], $db, $responsediagnos)); 
            } 
            return $inspections; 
        } else { 
            http_response_code(400); 
            echo json_encode(["Bad Request"]);
            exit; 
        } 
    } 
    foreach($consultations as $consultationId){ 
        if($grouped) 
        { 
            $massage = "SELECT i.*  
            FROM inspection i  
            JOIN consultation c ON i.id = c.inspectionid  
            "; 
 
            if (!empty($responsediagnos)) { 
 
                $massage .= "JOIN diagnos d ON i.id = d.inspectionid "; 
            } 
 
            $massage .= "WHERE i.id=:id AND i.previousinspectionid IS NULL "; 
 
            if (!empty($responsediagnos)) { 
 
                $massage .= "AND d.id IN (:icdIds)"; 
            } 
 
            $massage .= "GROUP BY i.id"; 
 
            if (!empty($responsediagnos)) { 
                $massage = str_replace(':icdIds', implode(', ', $filteredWithQuotes), $massage); 
            } 
 
            $stmt = $db->prepare($massage); 
            $stmt->bindValue(':id', $consultationId['inspectionid']); 
            $stmt->execute(); 
            $inspection = $stmt->fetch(PDO::FETCH_ASSOC); 
            if($stmt->rowCount() > 0){ 
                $inspections = getInspectionChain($inspection['id'], $db, $responsediagnos); 
            } 
        } 
 
        else{ 
            $massage = "SELECT i.*  
            FROM inspection i  
            JOIN consultation c ON i.id = c.inspectionid  
            "; 
 
            if (!empty($responsediagnos)) {
                $massage .= "JOIN diagnos d ON i.id = d.inspectionid "; 
            } 
 
            $massage .= "WHERE i.id = :id "; 
 
            if (!empty($responsediagnos)) { 
                $massage .= "AND d.id IN (:icdIds) "; 
            } 
 
            $massage .= "GROUP BY i.id"; 
 
            if (!empty($responsediagnos)) { 
                $massage = str_replace(':icdIds', implode(', ', $filteredWithQuotes), $massage); 
            } 
             
            $stmt = $db->prepare($massage); 
            $stmt->bindValue(':id', $consultationId['inspectionid']); 
            $stmt->execute(); 
            $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC); 
            foreach ($inspections as $inspection) 
            { 
                $massage = "SELECT name FROM doctor WHERE id = :id"; 
                $stmt = $db->prepare($massage); 
                $stmt->bindValue(':id', $inspection['author']); 
                $stmt->execute(); 
                $doctor = $stmt->fetch(PDO::FETCH_ASSOC); 
         
                $massage = "SELECT name FROM patient WHERE id = :id"; 
                $stmt = $db->prepare($massage); 
                $stmt->bindValue(':id', $inspection['patientid']); 
                $stmt->execute(); 
                $patient = $stmt->fetch(PDO::FETCH_ASSOC); 
         
                $massage = "SELECT id,icdDiagnosisID,description,type,createtime FROM diagnos WHERE inspectionid = :id"; 
                $stmt = $db->prepare($massage); 
                $stmt->bindValue(':id', $inspection['id']); 
                $stmt->execute(); 
                $diagnoses = $stmt->fetchAll(PDO::FETCH_ASSOC); 
         
                $Ndiagnoses=[]; 
                foreach ($diagnoses as $diagnos) 
                { 
                    $massage = "SELECT mkb_code,mkb_name FROM icd10 WHERE id = :id"; 
                    $stmt = $db->prepare($massage); 
                    $stmt->bindValue(':id', $diagnos['icddiagnosisid']); 
                    $stmt->execute(); 
                    $icd = $stmt ->fetch(PDO::FETCH_ASSOC); 
                    $Ndiagnos = [ 
                        "id" => $diagnos["id"], 
                        "createTime" => $diagnos["createtime"], 
                        "code" => $icd["mkb_code"], 
                        "name" => $icd["mkb_name"], 
                        "description" => $diagnos["description"], 
                        "type" => $diagnos["type"], 
                    ]; 
                    $Ndiagnoses[] = $Ndiagnos; 
                } 
 
                $inspectionData = [ 
                    "id" => $inspection["id"], 
                    "createTime" => $inspection["createtime"], 
                    "previousId" => $inspection["previousinspectionid"], 
                    "date" => $inspection["date"], 
                    "conclusion" => $inspection["conclusion"], 
                    "doctorId" => $inspection["author"], 
                    "doctor" => $doctor['name'], 
                    "patientId" => $inspection["patientid"], 
                    "patient" => $patient['name'], 
                    "diagnosis" => $Ndiagnoses, 
                ]; 
                $inspections[] = $inspectionData; 
            } 
        } 
    } 
    function paginateInspections($inspections, $page, $size) { 
        $page = (int) $page; 
        $size = (int) $size; 
     
        if ($page < 1) {$page = 1;} 
        if ($size < 1) {$size = 10;} 
         
        $startIndex = ($page - 1) * $size; 
        $endIndex = $startIndex + $size; 
        $endIndex = min($endIndex, count($inspections)); 
 
        $paginatedInspections = array_slice($inspections, $startIndex, $endIndex - $startIndex); 
        return $paginatedInspections; 
    } 
    $paginatedInspections = paginateInspections($inspections, $page, $size); 
    $pagination = [ 
        "size"=> $size, 
        "count"=> count($inspections), 
        "current"=>$page, 
    ]; 
    $response = [ 
        "inspections" => $paginatedInspections, 
        "pagination"=> $pagination, 
    ];
    http_response_code(200); 
    echo json_encode(["Patient inspections list retrived"]); 
    echo json_encode($response);
}else if (preg_match('/\/api\/consultation\.php\/([0-9a-f\-]+)\/comment$/i', $_SERVER['REQUEST_URI'], $matches) && $_SERVER['REQUEST_METHOD'] === 'POST'){
    if (true){
        $data = json_decode(file_get_contents('php://input'), true);
        $headers = getallheaders();
        $token = "";
        if (isset($headers['Authorization'])) {
            $token = explode(' ', $headers['Authorization'])[1];
        }
        $massage = "SELECT id, speciality FROM doctor WHERE token = :token";
        $stmt = $db->prepare($massage);
        $stmt->bindValue(':token', $token);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            http_response_code(401);
            echo json_encode(['Unauthorized']);
            exit;
        }
        $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!isset($data['content'])) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }
    $consultationid=$matches[1];

    $massage = "SELECT inspectionid, specialityid FROM consultation WHERE id = :consultationid";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':consultationid', $consultationid);
    $stmt->execute();
    $author = $stmt->fetch(PDO::FETCH_ASSOC);

    $massage = "SELECT author FROM inspection WHERE id = :inspectionid";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':inspectionid', $author['inspectionid']);
    $stmt->execute();
    $ins = $stmt->fetch(PDO::FETCH_ASSOC);

    if($ins['author']!= $doctor['id'] && $author['specialityid']!= $doctor['speciality']){
            http_response_code(403);
            echo json_encode(["doctor doesn't have add comment to consultation (unsuitable specialty and not the inspection author)"]);

    }else{

        $Commid = uniqid();
        $createdAt = date('Y-m-d\TH:i:s.u\Z');
        $massage = "INSERT INTO comment (id,consultationid,parentid,author,content,createtime,modifytime) VALUES (:id,:consultationid,:parentid,:author,:content,:createtime,:createtime)";
        $stmt = $db->prepare($massage);
        $stmt->bindValue(':id', $Commid);
        $stmt->bindValue(':parentid', $data['parentId']);
        $stmt->bindValue(':consultationid', $consultationid);
        $stmt->bindValue(':author', $doctor['id']);
        $stmt->bindValue(':content', $data['content']);
        $stmt->bindValue(':createtime', $createdAt);

        try {
            $stmt->execute();
            header('Content-type: application/json');
            http_response_code(200);
            echo json_encode(['Sucsess'=>$Commid]);

        } catch (PDOException $e) {
            http_response_code(400);
            echo json_encode(['Invalid arguments']);
        }
    }
}elseif (preg_match('/\/api\/consultation\.php\/comment\/([0-9a-f\-]+)$/i', $_SERVER['REQUEST_URI'], $matches) && $_SERVER['REQUEST_METHOD'] === 'PUT'){
    if (true){
        $data = json_decode(file_get_contents('php://input'), true);
        $headers = getallheaders();
        $token = "";
        if (isset($headers['Authorization'])) {
            $token = explode(' ', $headers['Authorization'])[1];
        }
        $massage = "SELECT id FROM doctor WHERE token = :token";
        $stmt = $db->prepare($massage);
        $stmt->bindValue(':token', $token);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            http_response_code(401);
            echo json_encode(['Unauthorized']);
            exit;
        }
    }
    
    if (!isset($data['content'])) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }
    
    $commentid=$matches[1];
    $massage = "SELECT author FROM comment WHERE id = :id";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':id', $commentid);
    $stmt->execute();
    $author = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['Comment not found']);
        exit;
    }

    $massage = "SELECT id FROM doctor WHERE token = :token";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':token', $token);
    $stmt->execute();
    $doctorid = $stmt->fetch(PDO::FETCH_ASSOC);
    if($author['author']!= $doctorid['id']){
        http_response_code(500);
        echo json_encode(["doctor is not the author of the comment"]);
    }
    else
    {
        $massage = "UPDATE comment SET content=:content WHERE id = :id";
        $stmt = $db->prepare($massage);
        $stmt->bindValue(':content', $data['content']);
        $stmt->bindValue(':id', $commentid);
        try {
            $stmt->execute();
            header('Content-type: application/json');
            http_response_code(200);
            echo json_encode(["Success"]);
    
        } catch (PDOException $e) {
            http_response_code(400);
            echo json_encode(['Invalid arguments']);
        }
    }
}else {
    http_response_code(404);
    echo json_encode(["message" => "Not Found"]);
}
$db = null;
?>