<?php
// config/database.php

class Database {
    private $host = '127.0.0.1';     // Use 127.0.0.1 for Windows/XAMPP
    private $db_name = 'healthhive'; // Your database name
    private $username = 'root';      // Default XAMPP user
    private $password = '';          // Default XAMPP password
    private $port = 3306;            // MySQL port
    private $conn = null;            // PDO connection

    // Create and return the database connection
    public function getConnection() {
        if ($this->conn !== null) {
            return $this->conn; // Reuse existing connection
        }

        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch (PDOException $exception) {
            // Log the error for debugging
            error_log("Database Connection Error: " . $exception->getMessage());
            // Stop execution if connection fails
            die("❌ Database Connection Error: Unable to connect to database. Please check your configuration.");
        }

        return $this->conn;
    }

    // Close the connection
    public function closeConnection() {
        $this->conn = null;
    }

    // Test database connection
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->query("SELECT 1");
            return $stmt !== false;
        } catch (PDOException $e) {
            error_log("Database test failed: " . $e->getMessage());
            return false;
        }
    }
}

// ✅ Create a single database connection instance
$database = new Database();

// ✅ Get the PDO connection
$pdo = $database->getConnection();

// ✅ Create global variable for legacy code (functions.php expects $db)
$db = $pdo;

// ✅ Make $db available globally for all included files
$GLOBALS['db'] = $db;

// ✅ Optional: Test connection and log status
if ($database->testConnection()) {
    // Connection successful - you can remove this in production
    // error_log("✅ Database connection successful");
} else {
    error_log("❌ Database connection test failed");
}

// ✅ Define database-related constants if needed
if (!defined('DB_CONNECTED')) {
    define('DB_CONNECTED', isset($db) && $db instanceof PDO);
}
?>