<?php
require 'vendor/autoload.php';

use Aws\Ssm\SsmClient;
use Aws\S3\S3Client;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$ssm = new SsmClient([
    'version' => 'latest',
    'region'  => $_ENV['AWS_REGION']
]);

function getParam($ssm, $name) {
    return $ssm->getParameter([
        'Name' => $name,
        'WithDecryption' => true
    ])['Parameter']['Value'];
}

$db_host = getParam($ssm, $_ENV['PARAMETER_STORE_DB_HOST']);
$db_user = getParam($ssm, $_ENV['PARAMETER_STORE_DB_USER']);
$db_pass = getParam($ssm, $_ENV['PARAMETER_STORE_DB_PASS']);
$db_name = getParam($ssm, $_ENV['PARAMETER_STORE_DB_NAME']);

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) die("DB connection failed");

$conn->query("CREATE TABLE IF NOT EXISTS contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(100),
  message TEXT,
  attachment VARCHAR(255)
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $message = $_POST['message'] ?? '';
    $attachment = '';

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $s3 = new S3Client([
            'version' => 'latest',
            'region'  => $_ENV['AWS_REGION']
        ]);
        $result = $s3->putObject([
            'Bucket' => $_ENV['S3_BUCKET_NAME'],
            'Key' => 'uploads/' . basename($_FILES['attachment']['name']),
            'SourceFile' => $_FILES['attachment']['tmp_name']
        ]);
        $attachment = $result['ObjectURL'];
    }

    $stmt = $conn->prepare("INSERT INTO contacts (name, email, message, attachment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $message, $attachment);
    echo $stmt->execute() ? "Message sent successfully!" : "Error: " . $stmt->error;
    $stmt->close();
}
$conn->close();
?>

