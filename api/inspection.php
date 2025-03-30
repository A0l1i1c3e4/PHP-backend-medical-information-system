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
}
if (preg_match('/\/api\/inspection\.php\/([0-9a-f\-]+)$/i', $_SERVER['REQUEST_URI'], $matches) && $_SERVER['REQUEST_METHOD'] === 'GET'){
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
    $inspectionid=$matches[1];
    $id = uniqid(); 
    $curentdate = date('Y-m-d\TH:i:s.u\Z');
    //Наличие инспекции
    $massage = "SELECT * FROM inspection WHERE id = :id";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':id', $inspectionid);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }
    else {
        $inspection = $stmt ->fetch(PDO::FETCH_ASSOC);
    }
    //Налицие пациента
    $patientid=$inspection['patientid'];
    $massage = "SELECT id,createtime,name,birthday,gender FROM patient WHERE id = :id";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':id', $patientid);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }
    else {
        $patient = $stmt ->fetch(PDO::FETCH_ASSOC);
    }

    //Налицие Автора(докотора)
    $doctorid=$inspection['author'];
    $massage = "SELECT id,createTime,name,birthday,gender,email,phone FROM doctor WHERE id = :id";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':id', $doctorid);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }
    else {
        $doctor = $stmt ->fetch(PDO::FETCH_ASSOC);
    }
    //Налицие диагноза
    $massage = "SELECT id,icdDiagnosisID,description,type,createtime FROM diagnos WHERE inspectionid = :id";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':id', $inspectionid);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
       /* http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;*/
    }
    else {
        $Ndiagnoses=[];
        $diagnoses = $stmt ->fetchAll(PDO::FETCH_ASSOC);
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
    }
    //Наличие консультации
    $massage = "SELECT id,createtime,inspectionid,specialityid FROM consultation WHERE inspectionid = :id";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':id', $inspectionid);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }
    else {
        $consultations = $stmt ->fetchAll(PDO::FETCH_ASSOC);
        $Cresponses=[];
        foreach($consultations as $consultation){
            $massage = "SELECT id,name,createTime FROM speciality WHERE id = :id";
            $stmt = $db->prepare($massage);
            $stmt->bindValue(':id', $consultation['specialityid']);
            $stmt->execute();
            $speciality = $stmt ->fetch(PDO::FETCH_ASSOC);

            $massage = "SELECT id,author,createTime,content,modifyTime FROM comment WHERE consultationid = :id AND parentid=id";
            $stmt = $db->prepare($massage);
            $stmt->bindValue(':id', $consultation['id']);
            $stmt->execute();
            $comment = $stmt ->fetch(PDO::FETCH_ASSOC);
            $massage = "SELECT id,createTime,name,birthday,gender,email,phone FROM doctor WHERE id = :id";
            $stmt = $db->prepare($massage);
            $stmt->bindValue(':id', $comment['author']);
            $stmt->execute();
            $author = $stmt ->fetch(PDO::FETCH_ASSOC);
            $Commresponse =[
                "id" => $comment["id"],
                "createTime" => $comment["createtime"],
                "content"=>$comment["content"],
                "author"=> $author,
                "modifyTime"=>$comment["modifytime"]
            ];
            $Cresponse = [
                "id" => $consultation["id"],
                "createTime" => $consultation["createtime"],
                "inspectionId" => $consultation["inspectionid"],
                "speciality" => $speciality,
                "rootComment" => $Commresponse,
            ];
            $Cresponses[] = $Cresponse;
        }
        $response = [
            "id" => $inspection["id"],
            "createTime" => $inspection["createtime"],
            "date" => $inspection["date"],
            "anamnesis" => $inspection["anamnesis"],
            "complaints" => $inspection["complaints"],
            "treatment" => $inspection["treatment"],
            "conclusion" => $inspection["conclusion"],
            "nextVisitDate" => $inspection["nextvisitdate"],
            "deathDate" => $inspection["deathdate"],
            "previousInspectionId" => $inspection["previousinspectionid"],
            "patient" => $patient,
            "doctor" => $doctor,
            "diagnoses" => $Ndiagnoses,
            "consultations" => $Cresponses
        ];
        header('Content-type: application/json');
        http_response_code(200);
        echo json_encode(["Inspection found and successfully extracted" => $response]);
    }
}elseif (preg_match('/\/api\/inspection\.php\/([0-9a-f\-]+)$/i', $_SERVER['REQUEST_URI'], $matches) && $_SERVER['REQUEST_METHOD'] === 'PUT'){
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
        $doctorid = $stmt->fetch(PDO::FETCH_ASSOC);
    }

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
    
    $inspectionid=$matches[1];
    $data = json_decode(file_get_contents('php://input'), true);
    $curentdate = date('Y-m-d\TH:i:s.u\Z');
    
    $massage = "SELECT * FROM inspection WHERE id = :id";
    $stmt = $db->prepare($massage);
    $stmt->bindValue(':id', $inspectionid);
    $stmt->execute();
    $author = $stmt->fetch(PDO::FETCH_ASSOC);

    if($author['author']!= $doctorid['id']){
        http_response_code(500);
        echo json_encode(["User doesn't have editing rights (not the inspection author)"]);
    }
    else{
        if ($stmt->rowCount() != 1) {
            http_response_code(400);
            echo json_encode(['Invalid arguments']);
        exit;
        }else{
            $massage = "UPDATE inspection SET anamnesis=:anamnesis,complaints=:complaints,treatment=:treatment,conclusion=:conclusion,nextVisitDate=:nextVisitDate,deathDate=:deathDate WHERE id = :id";
            $stmt = $db->prepare($massage);
            $stmt->bindValue(':id', $inspectionid);
            $stmt->bindValue(':anamnesis', $data['anamnesis']);
            $stmt->bindValue(':complaints', $data['complaints']);
            $stmt->bindValue(':treatment', $data['treatment']);
            $stmt->bindValue(':conclusion', $data['conclusion']);
            $stmt->bindValue(':nextVisitDate', $data['nextVisitDate']);
            $stmt->bindValue(':deathDate', $data['deathDate']);
            $stmt->execute();
            
            $massage = "DELETE FROM diagnos WHERE inspectionid = :id";
            $stmt = $db->prepare($massage);
            $stmt->bindParam(':id', $inspectionid);
            $stmt->execute();
            
            foreach ($data['diagnoses'] as $diagnosis) {
                $diagnosid = uniqid();
                $massage = "INSERT INTO diagnos (id,icdDiagnosisID,inspectionid,description,type,createtime) VALUES (:id,:icddiagnosisid,:inspectionid,:description,:type,:createtime)";
                $stmt = $db->prepare($massage);
                $stmt->bindValue(':id', $diagnosid);
                $stmt->bindValue(':icddiagnosisid', $diagnosis['icdDiagnosisId']);
                $stmt->bindValue(':inspectionid', $inspectionid);
                $stmt->bindValue(':description', $diagnosis['description']);
                $stmt->bindValue(':type', $diagnosis['type']);
                $stmt->bindValue(':createtime', $curentdate);
                $stmt->execute();
            }
            http_response_code(200);
            echo json_encode(["Sucсess"]);
        }
    }
}elseif (preg_match('/\/api\/inspection\.php\/([0-9a-f\-]+)\/chain$/i', $_SERVER['REQUEST_URI'], $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
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
    $inspectionId = $matches[1];
    $inspections = [];
    function chain($inspectionId, $db) {
        $massage = "SELECT * FROM inspection WHERE id = :inspectionId";
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
            $stmt = $db->prepare($massage);
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

            $massage = "SELECT id FROM inspection WHERE previousinspectionid = :id";
            $stmt = $db->prepare($massage);
            $stmt->bindValue(':id', $inspectionId);
            $stmt->execute();

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

            if (!is_null($inspection["previousinspectionid"])) {
                $inspections = array_merge($inspections, chain($inspection["previousinspectionid"], $db));
            }
            return $inspections;
        } else {
            http_response_code(400);
            echo json_encode(["Bad Request"]);
        }
    }

    $inspections = chain($inspectionId, $db);
    http_response_code(200);
    echo json_encode(["Success" => $inspections]);
}else {
    http_response_code(404);
    echo json_encode(["Not Found"]);
}
$db = null;
?>