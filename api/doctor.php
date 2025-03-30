<?php
$dbname = "pgsql:host=localhost;dbname=Hospital";
$user = 'postgres';
$password = '123';
enum Gender: string{
    const Male = 'Male';
    const Female = 'Female';
}
try {
    $db = new PDO($dbname, $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["Internal Server Error"]);
    exit;
}

function tokenget() {
    $length = 10;
    $box = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $box[rand(0, strlen($box) - 1)];
    }
    return $randomString;
}

if ($_SERVER['REQUEST_URI'] === '/api/doctor.php/register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['name'], $data['password'], $data['gender'], $data['speciality'])) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }
    if (($data['gender'] != 'Male') && ($data['gender'] !='Female')) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }

    $message = "SELECT 1 FROM doctor WHERE email = :email";
    $stmt = $db->prepare($message);
    $stmt->bindValue(':email', $data['email']);
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }
    
    
    $token = tokenget();
    $doctorId = uniqid();
    $curentdate = date('Y-m-d\TH:i:s.u\Z'); 
    
    $message = "INSERT INTO doctor (id, createTime, name, password, email, birthday, gender, phone, speciality, token) VALUES (:id, :createTime, :name, :password, :email, :birthday, :gender, :phone, :speciality, :token)";
    $stmt = $db->prepare($message);
    $stmt->bindValue(':id', $doctorId);
    $stmt->bindValue(':createTime', $curentdate);
    $stmt->bindValue(':name', $data['name']);
    $stmt->bindValue(':password', $data['password']);
    $stmt->bindValue(':email', $data['email']);
    $stmt->bindValue(':birthday', $data['birthday']);
    $stmt->bindValue(':gender', $data['gender']);
    $stmt->bindValue(':phone', $data['phone']);
    $stmt->bindValue(':speciality', $data['speciality']);
    $stmt->bindValue(':token', $token);

    try {
        $stmt->execute();
        http_response_code(200);
        echo json_encode(['Doctor was registered', 'token' => $token]);

    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
    }

    $db = null;

}else if ($_SERVER['REQUEST_URI'] === '/api/doctor.php/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $message = "SELECT id FROM doctor WHERE email = :email and password = :password";
    $stmt = $db->prepare($message);
    $stmt->bindValue(':email', $data['email']);
    $stmt->bindValue(':password', $data['password']);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }
    else {
        $doctor = $stmt ->fetch(PDO::FETCH_ASSOC);
    }
    $token = tokenget();
    $message = "UPDATE doctor SET token = :token WHERE id = :id";
    $stmt = $db->prepare($message);
    $stmt->bindValue(':id', $doctor['id']);
    $stmt->bindValue(':token', $token);
    $stmt->execute();
    http_response_code(200);
    echo json_encode(['Success token' => $token]);

}else if ($_SERVER['REQUEST_URI'] === '/api/doctor.php/profile' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if(true){
        $headers = getallheaders();
        $token = "";
        if (isset($headers['Authorization'])) {
            $token = explode(' ', $headers['Authorization'])[1];
        }
        $message = "SELECT id,createTime,name,birthday,gender,email,phone FROM doctor WHERE token = :token";
        $stmt = $db->prepare($message);
        $stmt->bindValue(':token', $token);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            http_response_code(401);
            echo json_encode(['Unauthorized']);
            exit;
        }
        $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    $response = [
        "id" => $doctor["id"],
        "createTime" => $doctor["createtime"], 
        "name" => $doctor["name"],
        "birthday" => $doctor["birthday"],
        "gender" => $doctor["gender"],
        "email" => $doctor["email"],
        "phone" => $doctor["phone"]
    ];

    header('Content-type: application/json');
    http_response_code(200);
    echo json_encode(["Success" => $response]);

} elseif ($_SERVER['REQUEST_URI'] === '/api/doctor.php/profile' && $_SERVER['REQUEST_METHOD'] === 'PUT') {
    if(true){
        $headers = getallheaders();
        $token = "";
        if (isset($headers['Authorization'])) {
            $token = explode(' ', $headers['Authorization'])[1];
        }
        $message = "SELECT id,createTime,name,birthday,gender,email,phone FROM doctor WHERE token = :token";
        $stmt = $db->prepare($message);
        $stmt->bindValue(':token', $token);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            http_response_code(401);
            echo json_encode(['Unauthorized']);
            exit;
        }
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['name'], $data['email'], $data['gender'])) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }
    if (($data['gender'] !='Male') && ($data['gender'] !='Female')) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
        exit;
    }

    $message = "UPDATE doctor SET email = :email, name = :name, birthday = :birthday, gender = :gender, phone = :phone WHERE token = :token";
    $stmt = $db->prepare($message);
    $stmt->bindValue(':token', $token);
    $stmt->bindValue(':email', $data['email']);
    $stmt->bindValue(':name', $data['name']);
    $stmt->bindValue(':birthday', $data['birthday']);
    $stmt->bindValue(':gender', $data['gender']);
    $stmt->bindValue(':phone', $data['phone']);

    try {
        $stmt->execute();
        header('Content-type: application/json');
        http_response_code(200);
        echo json_encode(["Success"]);

    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['Invalid arguments']);
    }
} elseif ($_SERVER['REQUEST_URI'] === '/api/doctor.php/logout' && $_SERVER['REQUEST_METHOD'] === 'POST'){
    if(true){   
        $headers = getallheaders();
        $token = "";
        if (isset($headers['Authorization'])) {
            $token = explode(' ', $headers['Authorization'])[1];
        }
        $message = "SELECT * FROM doctor WHERE token = :token";
        $stmt = $db->prepare($message);
        $stmt->bindValue(':token', $token);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            http_response_code(401);
            echo json_encode(['Unauthorized']);
            exit;
        }
    }
    $message = "UPDATE doctor SET token = NULL WHERE token = :token";
    $stmt = $db->prepare($message);
    $stmt->bindValue(':token', $token);
    $stmt->execute();
    http_response_code(200);
    echo json_encode(['Sucsess']);
}else {
    http_response_code(404);
    echo json_encode(["Not Found"]);
}

?>  