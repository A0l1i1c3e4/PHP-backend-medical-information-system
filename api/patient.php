<?php

// Подключение к базе данных с использованием PDO
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

if ($_SERVER['REQUEST_URI'] === '/api/patient.php' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (true){
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
    if (!isset($data['name'], $data['gender'])) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }

    $createdAt = date('Y-m-d\TH:i:s.u\Z');
    $patientId = uniqid(); 

    $massage = "INSERT INTO patient (id, name, birthday, gender, createTime) VALUES (:id, :name, :birthday, :gender, :createTime)";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':id', $patientId);
    $stmt->bindValue(':name', $data['name']);
    $stmt->bindValue(':birthday', $data['birthday']);
    $stmt->bindValue(':gender', $data['gender']);
    $stmt->bindValue(':createTime', $createdAt);
    try {
        $stmt->execute();
        header('Content-type: application/json');
        http_response_code(200);
        echo json_encode(["Patient was registered" => $patientId]);

    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
    }

} elseif (preg_match('/\/api\/patient\.php\/([0-9a-f\-]+)\/inspections$/i', $_SERVER['REQUEST_URI'], $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (true){
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
    $patientId=$matches[1];
    $massage = "SELECT * FROM patient WHERE id = :patientId";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':patientId', $patientId);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }

    $massage = "SELECT id FROM doctor WHERE token = :token";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':token', $token);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $token =$user['id'];
    $id = uniqid(); 
    $createdAt = date('Y-m-d\TH:i:s.u\Z');
    
    if (!isset($data['date'], $data['anamnesis'], $data['complaints'], $data['anamnesis'], $data['treatment'], $data['conclusion'], $data['diagnoses']) || !($data['conclusion']== 'Disease' || $data['conclusion']== 'Recovery' || $data['conclusion']== 'Death')) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }
    if ($data['conclusion']== 'Death') {
        if($data['nextVisitDate']!=null || $data['deathDate']==null){
            http_response_code(400);
            echo json_encode(['Invalid arguments']);
            exit;
        }
    }else{
        if($data['deathDate']!=null){
            http_response_code(400);
            echo json_encode(['Invalid arguments']);
            exit;
        }
    }


    $diagnosflag=0;
    foreach ($data['diagnoses'] as $diagnosis) {
        if (!isset($diagnosis['icdDiagnosisId'], $diagnosis['type']) || !($diagnosis['type']== 'Main' || $diagnosis['type']== 'Concomitant' || $diagnosis['type']== 'Complication')) {
            http_response_code(400);
            echo json_encode(['Invalid arguments']);
            exit;
        }
        if($diagnosis['type']== 'Main'){
            $diagnosflag+=1;
        }
        $stmt = $db->prepare('SELECT * FROM icd10 WHERE id=:id;');
        $stmt->bindValue(':id', $diagnosis['icdDiagnosisId']);
        try {
            $stmt->execute();    
        } catch (PDOException $e) {
            http_response_code(400);
            echo json_encode(['Invalid arguments']);
            exit;
        }
        if ($stmt->rowCount() === 0) {
            http_response_code(400);
            echo json_encode(['Invalid arguments']);
            exit;
        }
    }
    if($diagnosflag != 1){
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }
    if(isset($data['consultations'])){
        foreach ($data['consultations'] as $consultation) {
            $temporary=$consultation['comment'];
            if (!isset($consultation['specialityId'], $consultation['comment'], $temporary['content'])) {
                http_response_code(400);
                echo json_encode(['Invalid arguments']);
                exit;
            }
            $stmt = $db->prepare('SELECT * FROM speciality WHERE id=:id;');
            $stmt->bindValue(':id', $consultation['specialityId']);
            try {
                $stmt->execute();    
            } catch (PDOException $e) {
                http_response_code(400);
                echo json_encode(['Invalid arguments']);
                exit;
            }
            if ($stmt->rowCount() === 0) {
                http_response_code(400);
                echo json_encode(['Invalid arguments']);
                exit;
            }
        }
    }

    $massage = "INSERT INTO inspection (id,createtime,patientid,date,anamnesis,complaints,treatment,conclusion,nextVisitDate,deathDate,previousInspectionid,author) VALUES (:id,:createtime,:patientid,:date,:anamnesis,:complaints,:treatment,:conclusion,:nextVisitDate,:deathDate,:previousInspectionId,:author)";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':id', $id);
    $stmt->bindValue(':createtime', $createdAt);
    $stmt->bindValue(':patientid', $patientId);
    $stmt->bindValue(':date', $data['date']);
    $stmt->bindValue(':anamnesis', $data['anamnesis']);
    $stmt->bindValue(':complaints', $data['complaints']);
    $stmt->bindValue(':treatment', $data['treatment']);
    $stmt->bindValue(':conclusion', $data['conclusion']);
    $stmt->bindValue(':nextVisitDate', $data['nextVisitDate']);
    $stmt->bindValue(':deathDate', $data['deathDate']);
    $stmt->bindValue(':previousInspectionId', $data['previousInspectionId']);
    $stmt->bindValue(':author', $token);
    try {
        $stmt->execute();
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }

    foreach ($data['diagnoses'] as $diagnosis) {
        $Did = uniqid();
        $massage = "INSERT INTO diagnos (id,icdDiagnosisID,inspectionid,description,type,createtime) VALUES (:id,:icddiagnosisid,:inspectionid,:description,:type,:createtime)";
        $stmt = $db->prepare($massage);
        $stmt->bindValue(':id', $Did);
        $stmt->bindValue(':icddiagnosisid', $diagnosis['icdDiagnosisId']);
        $stmt->bindValue(':inspectionid', $id);
        $stmt->bindValue(':description', $diagnosis['description']);
        $stmt->bindValue(':type', $diagnosis['type']);
        $stmt->bindValue(':createtime', $createdAt);
        $stmt->execute();
    }

    foreach ($data['consultations'] as $consultation) {
        $Cid = uniqid();
        $massage = "INSERT INTO consultation (id,inspectionid,specialityId,createtime) VALUES (:id,:inspectionid,:specialityId,:createtime)";
        $stmt = $db->prepare($massage);
        $stmt->bindValue(':id', $Cid);
        $stmt->bindValue(':inspectionid', $id);
        $stmt->bindValue(':specialityId', $consultation['specialityId']);
        $stmt->bindValue(':createtime', $createdAt);
        $stmt->execute();

        if (isset($consultation['comment']) && isset($consultation['comment']['content'])) {
            $Commid = uniqid();
            $massage = "INSERT INTO comment (id,consultationid,parentid,author,content,createtime,modifytime) VALUES (:id,:consultationid,:id,:author,:content,:createtime,:createtime)";
            $stmt = $db->prepare($massage);
            $stmt->bindValue(':id', $Commid);
            $stmt->bindValue(':consultationid', $Cid);
            $stmt->bindValue(':author', $token);
            $stmt->bindValue(':content', $consultation['comment']['content']);
            $stmt->bindValue(':createtime', $createdAt);
            $stmt->execute();
        }
    }

    header('Content-type: application/json');
    http_response_code(200);
    echo json_encode(["Success" => $id]);
}elseif (preg_match('/\/api\/patient\.php\/([0-9a-f\-]+)\/inspections\/\??.*$/i', $_SERVER['REQUEST_URI'], $matches) && $_SERVER['REQUEST_METHOD'] === 'GET'){
    if (true){
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
    $patientId = $matches[1];
    $massage = "SELECT * FROM patient WHERE id = :patientId";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':patientId', $patientId);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }
    $inspections = [];
    $request = $_GET['request'];
    if(isset($request)){
        $massage = "SELECT DISTINCT i.*, ic.mkb_code, ic.mkb_name
        FROM inspection i
        JOIN diagnos d ON i.id = d.inspectionid
        JOIN icd10 ic ON d.icdDiagnosisid = ic.id
        WHERE patientid=:patientid AND i.previousinspectionid IS NULL
        AND (ic.mkb_code ILIKE :icd_part OR ic.mkb_name ILIKE :icd_part) ";

        $stmt = $db->prepare($massage);
        $stmt->bindValue(':icd_part', '%' . $request . '%');
        $stmt->bindValue(':patientid', $patientId);
        $stmt->execute();
        $Pinspection = $stmt->fetchAll(PDO::FETCH_ASSOC); 

        foreach ($Pinspection as $inspection)
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
    
            $Dresponses=[];
            foreach ($diagnoses as $diagnos)
            {
                $massage = "SELECT mkb_code,mkb_name FROM icd10 WHERE id = :id";
                $stmt = $db->prepare($massage);
                $stmt->bindValue(':id', $diagnos['icddiagnosisid']);
                $stmt->execute();
                $icd = $stmt ->fetch(PDO::FETCH_ASSOC);
                $Dresponse = [
                    "id" => $diagnos["id"],
                    "createTime" => $diagnos["createtime"],
                    "code" => $icd["mkb_code"],
                    "name" => $icd["mkb_name"],
                    "description" => $diagnos["description"],
                    "type" => $diagnos["type"],
                ];
                $Dresponses[] = $Dresponse;
            }

            $inspectionData = [
                "id" => $inspection["id"],
                "createTime" => $inspection["createtime"],
                "date" => $inspection["date"],
                "diagnosis" => $Dresponses,
            ];
            $inspections[] = $inspectionData;
        }
    }
    http_response_code(200);
    echo json_encode(["Patient inspections list retrived"]);
    echo json_encode([$inspections]);
}elseif (preg_match('/\/api\/patient\.php\/([0-9a-f\-]+)\/inspections\??.*$/i', $_SERVER['REQUEST_URI'], $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (true){
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
    
    $patientId = $matches[1];
    $massage = "SELECT * FROM patient WHERE id = :patientId";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':patientId', $patientId);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }
    
    $grouped = filter_var($_GET['grouped'], FILTER_VALIDATE_BOOLEAN) ?? false;
    $page = $_GET['page'] ?? 1;
    $size = $_GET['size'] ?? 10;
    $flag=true;
    if($_GET['icdRoots']!= ''){
        $roots = explode(',', $_GET['icdRoots']);
        $flag=true;
    }else {$roots = [null]; $flag=false;}

    $massage = "SELECT * FROM diagnos" ;
    $stmt = $db->prepare($massage);
    $stmt->execute();
    $diagnoses=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $filtered = [];
    if (!$flag){

    }
    else{
        foreach($diagnoses as $diagnos){

            $idparent = 0;
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
                    $filtered[] = $diagnos['id'];
                    break;
                }
            }
        }
        if (empty($filtered)) {
            $filtered[]=null;
        }
    }

    $inspections = [];
    
    function getInspectionChain($inspectionId, $db, $patientId, $filtered) {

        $massage = "SELECT i.* 
        FROM inspection i 
        ";

        if (!empty($filtered)) {
            $massage .= "JOIN diagnos d ON i.id = d.inspectionid ";
        }

        $massage .= "WHERE i.id = :inspectionId AND patientid=:patientid ";

        if (!empty($filtered)) {
            $massage .= "AND d.id IN (:icdIds) ";
        }

        $massage .= "GROUP BY i.id";
        if (!empty($filtered)) {
            $massage = str_replace(':icdIds', implode(', ', $filtered), $massage);
        }

        $stmt = $db->prepare($massage);
        $stmt->bindValue(':inspectionId', $inspectionId);
        $stmt->bindValue(':patientid', $patientId);
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
            $stmt = $db->prepare($massage);
            $stmt->bindValue(':id', $inspectionId);
            $stmt->execute();
            $diagnoses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $Dresponses=[];
            foreach ($diagnoses as $diagnos)
            {
                $massage = "SELECT mkb_code,mkb_name FROM icd10 WHERE id = :id";
                $stmt = $db->prepare($massage);
                $stmt->bindValue(':id', $diagnos['icddiagnosisid']);
                $stmt->execute();
                $icd = $stmt ->fetch(PDO::FETCH_ASSOC);
                $Dresponse = [
                    "id" => $diagnos["id"],
                    "createTime" => $diagnos["createtime"],
                    "code" => $icd["mkb_code"],
                    "name" => $icd["mkb_name"],
                    "description" => $diagnos["description"],
                    "type" => $diagnos["type"],
                ];
                $Dresponses[] = $Dresponse;
            }

            $massage = "SELECT i.* 
            FROM inspection i 
            ";

            if (!empty($filtered)) {
                $massage .= "JOIN diagnos d ON i.id = d.inspectionid ";
            }

            $massage .= "WHERE previousinspectionid = :id AND patientid=:patientid ";

            if (!empty($filtered)) {
                $massage .= "AND d.id IN (:icdIds) OR d.id IS NULL ";
            }

            $massage .= "GROUP BY i.id";
            $massage = str_replace(':icdIds', implode(', ', $filtered), $massage);

            $stmt = $db->prepare($massage);
            $stmt->bindValue(':id', $inspectionId);
            $stmt->bindValue(':patientid', $patientId);
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
                "diagnosis" => $Dresponses,
                "hasChain" => !is_null($inspection["previousinspectionid"]),
                "hasNested" => $stmt->rowCount() > 0
            ];

            $inspections[] = $inspectionData;
            
            foreach($nests as $nest)
            {
                $inspections = array_merge($inspections, getInspectionChain( $nest['id'], $db, $patientId, $filtered));
            }
            return $inspections;
        } else {
            http_response_code(400);
            echo json_encode(["Bad Request"]);
        }
    }
    if($grouped)
    {
        $massage = "SELECT i.* 
        FROM inspection i 
        ";

        if (!empty($filtered)) {
            $massage .= "JOIN diagnos d ON i.id = d.inspectionid ";
        }

        $massage .= "WHERE i.patientid = :id AND i.previousinspectionid IS NULL ";

        if (!empty($filtered)) {
            $massage .= "AND d.id IN (:icdIds)";
        }

        $massage .= "GROUP BY i.id";

        if (!empty($filtered)) {
            $filteredWithQuotes = array_map(function($value) {
                return "'$value'"; 
            }, $filtered);
            $filtered=$filteredWithQuotes;
            $massage = str_replace(':icdIds', implode(', ', $filteredWithQuotes), $massage);
        }

        $stmt = $db->prepare($massage);
        $stmt->bindValue(':id', $patientId);
        $stmt->execute();
        $inspection = $stmt->fetch(PDO::FETCH_ASSOC);
        if($stmt->rowCount() > 0){
            $inspections = getInspectionChain($inspection['id'], $db, $patientId, $filtered);
        }
    }

    else{
        $massage = "SELECT i.* 
        FROM inspection i 
        ";

        if (!empty($filtered)) {
            $massage .= "JOIN diagnos d ON i.id = d.inspectionid ";
        }

        $massage .= "WHERE i.patientid = :id ";

        if (!empty($filtered)) {
            $massage .= "AND d.id IN (:icdIds) ";
        }

        $massage .= "GROUP BY i.id";
        if (!empty($filtered)) {
            $filteredWithQuotes = array_map(function($value) {
                return "'$value'"; 
            }, $filtered);
            $filtered=$filteredWithQuotes;
            $massage = str_replace(':icdIds', implode(', ', $filteredWithQuotes), $massage);
        }
        
        $stmt = $db->prepare($massage);
        $stmt->bindValue(':id', $patientId);
        $stmt->execute();
        $Pinspection = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($Pinspection as $inspection)
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
    
            $Dresponses=[];
            foreach ($diagnoses as $diagnos)
            {
                $massage = "SELECT mkb_code,mkb_name FROM icd10 WHERE id = :id";
                $stmt = $db->prepare($massage);
                $stmt->bindValue(':id', $diagnos['icddiagnosisid']);
                $stmt->execute();
                $icd = $stmt ->fetch(PDO::FETCH_ASSOC);
                $Dresponse = [
                    "id" => $diagnos["id"],
                    "createTime" => $diagnos["createtime"],
                    "code" => $icd["mkb_code"],
                    "name" => $icd["mkb_name"],
                    "description" => $diagnos["description"],
                    "type" => $diagnos["type"],
                ];
                $Dresponses[] = $Dresponse;
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
                "diagnosis" => $Dresponses,
            ];
            $inspections[] = $inspectionData;
        }
    }
    function paginateInspections($inspections, $page, $size) {
        $page = (int) $page;
        $size = (int) $size;
    
        if ($page < 1) {
            $page = 1;
        }
        if ($size < 1) {
            $size = 10; 
        }
        
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
}elseif (preg_match('/\/api\/patient\.php\/([0-9a-f\-]+)$/i', $_SERVER['REQUEST_URI'], $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (true){
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

    $patientid=$matches[1];
    $sql = "SELECT * FROM patient WHERE id = :patientid";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':patientid', $patientid);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }    
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    $response = [
        'id' => $patient["id"],
        'createTime' => $patient["createtime"],
        "name" => $patient["name"],
        "birthday" => $patient["birthday"],
        "gender" => $patient["gender"],
        ];
    header('Content-type: application/json');
    http_response_code(200);
    echo json_encode(["Success" => $response]);

}elseif (strpos($_SERVER['REQUEST_URI'], '/api/patient.php')!== false && $_SERVER['REQUEST_METHOD'] === 'GET'){
    $data = json_decode(file_get_contents('php://input'), true);
    if(TRUE){    
        $headers = getallheaders();
        $token = "";
        if (isset($headers['Authorization'])) {
            $token = explode(' ', $headers['Authorization'])[1];
            }
            $massage = "SELECT * FROM doctor WHERE token = :token";
            $stmt = $db->prepare($massage);
            $stmt->bindValue(':token', $token);
            $stmt->execute();
        if ($stmt->rowCount() === 0) {
            http_response_code(401);
            echo json_encode(['Unauthorized']);
           exit;
        }
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!isset($name) || !isset($conclusions) || !isset($sorting) || !isset($sheduledVisits) || !isset($onlyMine) || !isset($page) || !isset($size) || !is_numeric($page) || !is_numeric($size)) {
        http_response_code(400);
        echo json_encode(["Invalid arguments for filtration/pagination/sorting"]);
        exit;
    }
    $name = $_GET['name'] ?? '';
    $conclusions = $_GET['conclusions'] ?? '';
    $sorting = $_GET['sorting'] ?? 'NameAsc';
    $sheduledVisits = filter_var($_GET['sheduledVisits'], FILTER_VALIDATE_BOOLEAN) ?? false;
    $onlyMine = filter_var($_GET['onlyMine'], FILTER_VALIDATE_BOOLEAN) ?? false;
    $page = $_GET['page'] ?? 1;
    $size = $_GET['size'] ?? 10;
    


    $sql = "SELECT patient.id AS id, patient.createTime AS createTime, patient.name AS name, patient.birthday AS birthday, patient.gender AS gender
        FROM patient 
        JOIN (
            SELECT patientId, MAX(createTime) as maxCreateTime
            FROM inspection
            GROUP BY patientId
        ) latest_inspection ON patient.id = latest_inspection.patientId
        JOIN inspection ON patient.id = inspection.patientId AND inspection.createTime = latest_inspection.maxCreateTime";

    if (!empty($name)) {
        $sql .= " WHERE patient.name ILIKE '%' || :name || '%'"; 
    } else {
        $sql .= " WHERE TRUE";
    }

    if ($conclusions!='') {
        $sql .= " AND inspection.conclusion = :conclusions";
    }

    if ($onlyMine && !empty($user)) {
        $sql .= " AND inspection.author = :doctorId";
    }

    if ($sheduledVisits) {
        $sql .= " AND inspection.nextVisitDate IS NOT NULL";
    }

    switch ($sorting) {
        case 'NameAsc':
            $sql .= " GROUP BY patient.id, patient.createTime, patient.name ORDER BY patient.name ASC";
            break;
        case 'NameDesc':
            $sql .= " GROUP BY patient.id, patient.createTime, patient.name ORDER BY patient.name DESC";
            break;
        case 'CreateAsc':
            $sql .= " GROUP BY patient.id, patient.createTime, patient.name ORDER BY patient.createTime ASC";
            break;
        case 'CreateDesc':
            $sql .= " GROUP BY patient.id, patient.createTime, patient.name ORDER BY patient.createTime DESC";
            break;
        case 'InspectionAsc':
            $sql .= " GROUP BY patient.id, patient.createTime, patient.name, inspection.nextVisitDate ORDER BY inspection.nextVisitDate ASC";
            break;
        case 'InspectionDesc':
            $sql .= " GROUP BY patient.id, patient.createTime, patient.name, inspection.nextVisitDate ORDER BY inspection.nextVisitDate DESC";
            break;
    }

    $offset = ($page - 1) * $size;
    $sql .= " LIMIT :size OFFSET :offset";
    $stmt = $db->prepare($sql);
    if (!empty($name)) {
        $stmt->bindParam(':name', $name);
    }
    if ($conclusions!='') {
        $stmt->bindParam(':conclusions', $conclusions);
    }
    if ($onlyMine=='true' && !empty($user)) {
        $stmt->bindParam(':doctorId', $user['id']);
    }
    $stmt->bindParam(':size', $size, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countSql = "SELECT COUNT(DISTINCT patient.id) 
                FROM patient 
                JOIN inspection ON patient.id = inspection.patientId";

    $countSql .= substr($sql, strpos($sql, ' WHERE'));
    $countSql = str_replace("LIMIT :size OFFSET :offset", "", $countSql);
    $countStmt = $db->prepare($countSql);

    $countStmt->bindParam(':name', $name);
    if ($conclusions!='') {
        $countStmt->bindParam(':conclusions', $conclusions);
    }if ($onlyMine=='true' && !empty($user)) {
        $countStmt->bindParam(':doctorId', $doctorId);
    }

    $countStmt->execute();
    $count = $countStmt->fetchColumn();

    $response = [
        'patients' => $patients,
        'pagination' => [
            'size' => $size,
            'count' => $count,
            'current' => $page
        ]
    ];

    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode($response);
}else {
    http_response_code(404);
    echo json_encode(["Not Found"]);
}
$db = null;
?>