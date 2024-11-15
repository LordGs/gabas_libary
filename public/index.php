<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require '../src/vendor/autoload.php';
$app = new \Slim\App;

$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "gabas_library";

// Insert Books_Authors
function insertBookAuthor($conn, $bookId, $authorId) {
    try {
        $sqlInsertBooksAuthors = "INSERT INTO books_authors (bookid, authorid) VALUES (:bookId, :authorId)";
        $stmtBooksAuthors = $conn->prepare($sqlInsertBooksAuthors);
        $stmtBooksAuthors->execute(['bookId' => $bookId, 'authorId' => $authorId]);
        return true; 
    } catch (PDOException $e) {
        return false; 
    }
}

// Function to generate a new JWT token
function generateToken($userid) {
    $key = 'server_hack';
    $iat = time();
    $payload = [
        'iss' => 'http://library.org',
        'aud' => 'http://library.com',
        'iat' => $iat,
        // 10 minutes
        'exp' => $iat + 600,
        "data" => array(
            "userid" => $userid
        )
    ];

    return JWT::encode($payload, $key, 'HS256');
}

// generate and replace token
function generateAndUpdateToken($conn, $userid) {
    $newToken = generateToken($userid);
    $sqlUpdateNewToken = "UPDATE users SET token = :token WHERE userid = :userid";
    $stmtUpdateNewToken = $conn->prepare($sqlUpdateNewToken);
    $stmtUpdateNewToken->execute(['userid' => $userid, 'token' => $newToken]);

    return $newToken;
}

// Register
$app->post('/user/register', function (Request $request, Response $response, array $args) use ($servername, $dbusername, $dbpassword, $dbname) {
    $data = json_decode($request->getBody());
    $usr = $data->username;
    $pass = $data->password;

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $dbusername, $dbpassword);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "INSERT INTO users (username, password) VALUES (:usr, :pass)";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['usr' => $usr, 'pass' => password_hash($pass, PASSWORD_DEFAULT)]);

        $response->getBody()->write(json_encode(array("status" => "success", "data" => null)));
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
    }
    $conn = null;

    return $response;
});

// authenticate
$app->post('/user/authenticate', function (Request $request, Response $response, array $args) use ($servername, $dbusername, $dbpassword, $dbname) {
    $data = json_decode($request->getBody());
    $usr = $data->username;
    $pass = $data->password;

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $dbusername, $dbpassword);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT * FROM users WHERE username=:usr";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['usr' => $usr]);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $data = $stmt->fetch();

        // Verify password
        if ($data && password_verify($pass, $data['password'])) {
            $userid = $data['userid'];
            $jwt = generateToken($userid);

            // Store the token in the users table
            $sqlUpdateToken = "UPDATE users SET token = :token WHERE userid = :userid";
            $stmtUpdateToken = $conn->prepare($sqlUpdateToken);
            $stmtUpdateToken->execute(['userid' => $userid, 'token' => $jwt]);

            $response->getBody()->write(json_encode(array("status" => "success", "token" => $jwt, "data" => null)));
        } else {
            $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => "Authentication Failed"))));
        }
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
    }

    return $response;
});

// Insert Books
$app->post('/book/add', function (Request $request, Response $response, array $args) use ($servername, $dbusername, $dbpassword, $dbname) {
    $data = json_decode($request->getBody());
    $bookTitle = $data->bookTitle;
    $authorName = $data->authorName;
    $token = $request->getHeader('Authorization')[0] ?? '';

    
    $token = str_replace('Bearer ', '', $token);

    try {
        // check token in users table
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $dbusername, $dbpassword);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // check if token exist
        $sqlCheckToken = "SELECT userid FROM users WHERE token = :token";
        $stmtCheckToken = $conn->prepare($sqlCheckToken);
        $stmtCheckToken->execute(['token' => $token]);

        if ($stmtCheckToken->rowCount() === 0) {
            return $response->withStatus(401)->write(json_encode(array("status" => "fail", "data" => array("title" => "Token is invalid or has been revoked."))));
        }

        $decoded = JWT::decode($token, new Key('server_hack', 'HS256'));
        $userid = $decoded->data->userid;

        // Insert book
        $sqlBook = "INSERT INTO books (title) VALUES (:title)";
        $stmtBook = $conn->prepare($sqlBook);
        $stmtBook->execute(['title' => $bookTitle]);
        $bookId = $conn->lastInsertId(); 

        // Insert author
        $sqlAuthor = "INSERT INTO authors (name) VALUES (:name)";
        $stmtAuthor = $conn->prepare($sqlAuthor);
        $stmtAuthor->execute(['name' => $authorName]);
        $authorId = $conn->lastInsertId(); 

        //call function above
        insertBookAuthor($conn, $bookId, $authorId);
        $newToken = generateAndUpdateToken($conn, $userid);
        $response->getBody()->write(json_encode(array("status" => "success", "Message" => "The book has been added to the collection", "newToken" => $newToken)));
    } catch (Exception $e) {
        $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
    }

    return $response;
});

// Update book
$app->put('/book/update', function (Request $request, Response $response, array $args) use ($servername, $dbusername, $dbpassword, $dbname) {
    $data = json_decode($request->getBody());
    $bookId = $data->bookId;
    $newBookTitle = $data->newBookTitle ?? null;
    $newAuthorName = $data->newAuthorName ?? null;
    $token = $request->getHeader('Authorization')[0] ?? '';

    
    $token = str_replace('Bearer ', '', $token);

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $dbusername, $dbpassword);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sqlCheckToken = "SELECT userid FROM users WHERE token = :token";
        $stmtCheckToken = $conn->prepare($sqlCheckToken);
        $stmtCheckToken->execute(['token' => $token]);

        if ($stmtCheckToken->rowCount() === 0) {
            return $response->withStatus(401)->write(json_encode(array("status" => "fail", "data" => array("title" => "Token is invalid or has been revoked."))));
        }

        $decoded = JWT::decode($token, new Key('server_hack', 'HS256'));
        $userid = $decoded->data->userid;

        // Update book title
        if ($newBookTitle) {
            $sqlUpdateBook = "UPDATE books SET title = :newTitle WHERE bookid = :bookId";
            $stmtUpdateBook = $conn->prepare($sqlUpdateBook);
            $stmtUpdateBook->execute(['newTitle' => $newBookTitle, 'bookId' => $bookId]);
        }

        // Update author name
        if ($newAuthorName) {
            $sqlUpdateAuthor = "UPDATE authors SET name = :newName WHERE authorid IN (SELECT authorid FROM books_authors WHERE bookid = :bookId)";
            $stmtUpdateAuthor = $conn->prepare($sqlUpdateAuthor);
            $stmtUpdateAuthor->execute(['newName' => $newAuthorName, 'bookId' => $bookId]);
        }

        $newToken = generateAndUpdateToken($conn, $userid);

        $response->getBody()->write(json_encode(array("status" => "success", "Message" => "The book has been updated", "newToken" => $newToken)));
    } catch (Exception $e) {
        $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
    }

    return $response;
});

// Delete book and author
$app->delete('/book/delete', function (Request $request, Response $response, array $args) use ($servername, $dbusername, $dbpassword, $dbname) {
    $data = json_decode($request->getBody());
    $collectionId = $data->collectionId; // Update this to use collectionId
    $token = $request->getHeader('Authorization')[0] ?? '';


    $token = str_replace('Bearer ', '', $token);

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $dbusername, $dbpassword);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sqlCheckToken = "SELECT userid FROM users WHERE token = :token";
        $stmtCheckToken = $conn->prepare($sqlCheckToken);
        $stmtCheckToken->execute(['token' => $token]);

        if ($stmtCheckToken->rowCount() === 0) {
            return $response->withStatus(401)->write(json_encode(array("status" => "fail", "data" => array("title" => "Token is invalid or has been revoked."))));
        }

        $decoded = JWT::decode($token, new Key('server_hack', 'HS256'));
        $userid = $decoded->data->userid;

        $sqlSelect = "SELECT bookid, authorid FROM books_authors WHERE collectionid = :collectionId";
        $stmtSelect = $conn->prepare($sqlSelect);
        $stmtSelect->execute(['collectionId' => $collectionId]);
        $result = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $bookId = $result['bookid'];
            $authorId = $result['authorid'];

            // Delete from books_authors
            $sqlDeleteBooksAuthors = "DELETE FROM books_authors WHERE collectionid = :collectionId";
            $stmtDeleteBooksAuthors = $conn->prepare($sqlDeleteBooksAuthors);
            $stmtDeleteBooksAuthors->execute(['collectionId' => $collectionId]);

            // Delete the book
            $sqlDeleteBook = "DELETE FROM books WHERE bookid = :bookId";
            $stmtDeleteBook = $conn->prepare($sqlDeleteBook);
            $stmtDeleteBook->execute(['bookId' => $bookId]);

            // Delete the author
            $sqlDeleteAuthor = "DELETE FROM authors WHERE authorid = :authorId";
            $stmtDeleteAuthor = $conn->prepare($sqlDeleteAuthor);
            $stmtDeleteAuthor->execute(['authorId' => $authorId]);

            // generate new token then update
            $newToken = generateToken($userid);
            $sqlUpdateNewToken = "UPDATE users SET token = :token WHERE userid = :userid";
            $stmtUpdateNewToken = $conn->prepare($sqlUpdateNewToken);
            $stmtUpdateNewToken->execute(['userid' => $userid, 'token' => $newToken]);

            $response->getBody()->write(json_encode(array("status" => "success", "message" => "Entry and related book/author deleted successfully.", "newToken" => $newToken)));
        } else {
            $response->getBody()->write(json_encode(array("status" => "fail", "message" => "No entry found for the given collectionId.")));
        }
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
    } catch (Exception $e) {
        $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
    }

    return $response;
});


//display
$app->get('/book/collection', function (Request $request, Response $response, array $args) use ($servername, $dbusername, $dbpassword, $dbname) {
    $data = json_decode($request->getBody());
    $collectionId = $data->collectionId; // Get collectionId from payload
    $token = $request->getHeader('Authorization')[0] ?? '';

    $token = str_replace('Bearer ', '', $token);

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $dbusername, $dbpassword);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sqlCheckToken = "SELECT userid FROM users WHERE token = :token";
        $stmtCheckToken = $conn->prepare($sqlCheckToken);
        $stmtCheckToken->execute(['token' => $token]);

        if ($stmtCheckToken->rowCount() === 0) {
            return $response->withStatus(401)->write(json_encode(array("status" => "fail", "data" => array("title" => "Token is invalid or has been revoked."))));
        }

        $decoded = JWT::decode($token, new Key('server_hack', 'HS256'));
        $userid = $decoded->data->userid;

        // Retrieve book and author details based on collectionId
        $sqlSelect = "
            SELECT b.bookid, b.title AS book_title, a.authorid, a.name AS author_name 
            FROM books_authors ba
            JOIN books b ON ba.bookid = b.bookid
            JOIN authors a ON ba.authorid = a.authorid
            WHERE ba.collectionid = :collectionId";
        
        $stmtSelect = $conn->prepare($sqlSelect);
        $stmtSelect->execute(['collectionId' => $collectionId]);
        $results = $stmtSelect->fetchAll(PDO::FETCH_ASSOC);

        // Generate a new token and update it in the users table
        $newToken = generateAndUpdateToken($conn, $userid);

        if ($results) {
            $response->getBody()->write(json_encode(array("status" => "success", "data" => $results, "newToken" => $newToken)));
        } else {
            $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => "No entries found for the given collectionId."), "newToken" => $newToken)));
        }
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
    } catch (Exception $e) {
        $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
    }
    return $response;
});

$app->run();
?>
