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

if ($_SERVER['REQUEST_URI'] === '/api/dictionary.php/icd10/icdRoots' && $_SERVER['REQUEST_METHOD'] === 'GET'){
    $stmt = $db->prepare('SELECT * FROM icd10 WHERE ID_PARENT IS NULL;');
    $stmt->execute();
    $icdRoots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode(["Root ICD-10 elements retrieved" => $icdRoots]);
}elseif (strpos($_SERVER['REQUEST_URI'], '/api/dictionary.php/icd10')!== false && $_SERVER['REQUEST_METHOD'] === 'GET') {

    $request = $_GET['request'];
    $page = $_GET['page'];
    $size = $_GET['size'];

    if (!isset($request) || !isset($page) || !isset($size) || !is_numeric($page) || !is_numeric($size)) {
        http_response_code(400);
        echo json_encode(["Some fields in request are invalid"]);
    }
    else{
        $offset = ($page - 1) * $size;

        $message = "SELECT id, mkb_code, mkb_name FROM icd10 WHERE MKB_CODE ILIKE '%' || :request || '%' OR MKB_NAME ILIKE '%' || :request || '%' ";
        $stmt = $db->prepare($message);
        $stmt->bindValue(':request', $request);
        $stmt->execute();
        $count = $stmt->rowCount();
        
        $stmt = $db->prepare($message . " LIMIT :size OFFSET :offset");
        $stmt->bindValue(':request', '%' . $request . '%');
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $speciality = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $speciality[] = [
                "id" => $row['id'], 
                "createTime" => date("Y-m-d\TH:i:s.u\Z"), 
                "code" => $row['mkb_code'],
                "name" => $row['mkb_name']
            ];
        }

        $response = [
            "records" => $speciality,
            "pagination" => [
                "size" => $size,
                "count" => $count,
                "current" => $page
            ]
        ];

        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode(["Searching result extracted" => $response]);
    }
}else if (strpos($_SERVER['REQUEST_URI'], '/api/dictionary.php/speciality')!== false && $_SERVER['REQUEST_METHOD'] === 'GET'){
    
    $name = $_GET['name'];
    $page = $_GET['page'];
    $size = $_GET['size'];

    if (!isset($name) || !isset($page) || !isset($size) || !is_numeric($page) || !is_numeric($size)) {
        http_response_code(400);
        echo json_encode(["Invalid arguments for filtration/pagination"]);
    }
    else{
        $offset = ($page - 1) * $size;

        $message = "SELECT id, createtime, name FROM speciality WHERE name ILIKE '%' || :name || '%' ";
        $stmt = $db->prepare($message);
        $stmt->bindValue(':name', $name);
        $stmt->execute();
        $count = $stmt->rowCount();
        
        $stmt = $db->prepare($message . " LIMIT :size OFFSET :offset");
        $stmt->bindValue(':name', '%' . $name . '%');
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $speciality = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $speciality[] = [
                "id" => $row['id'], 
                "createTime" => $row['createtime'],
                "name" => $row['name']
            ];
        }

        $response = [
            "records" => $speciality,
            "pagination" => [
                "size" => $size,
                "count" => $count,
                "current" => $page
            ]
        ];
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode(["Specialties paged list retrieved" => $response]);
    }
}else {
    http_response_code(404);
    echo json_encode(["Not Found"]);
}
$db = null;
?>