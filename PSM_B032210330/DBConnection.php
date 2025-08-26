<?php
class DBConnection {
    private $conn;

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        // Database configuration

$conn = new mysqli($host, $user, $pass, $dbname);
        $host = 'localhost'; // Database host
        $username = 'PSM_B032210330'; // Database username 
        $password = 'nutrieats1'; // Database password 
        $database = 'PSM_B032210330'; // Database name

        // Create connection
        $this->conn = new mysqli($host, $username, $password, $database);

        // Check connection
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    public function getConnection() {
        return $this->conn;
    }

    public function closeConnection() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

?>
