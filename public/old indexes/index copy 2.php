<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require '../src/vendor/autoload.php';
$app = new \Slim\App;

// Database connection details
$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "library";



// insert Books_Authors
function insertBookAuthor($conn, $bookId, $authorId) {
    try {
        $sqlInsertBooksAuthors = "INSERT INTO books_authors (bookid, authorid) VALUES (:bookId, :authorId)";
        $stmtBooksAuthors = $conn->prepare($sqlInsertBooksAuthors);
        $stmtBooksAuthors->execute(['bookId' => $bookId, 'authorId' => $authorId]);
        return true; // Indicate success
    } catch (PDOException $e) {
        // Handle the exception (log it, rethrow it, etc.)
        return false; // Indicate failure
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
        //10 minutes
        'exp' => $iat + 600, 
        "data" => array(
            "userid" => $userid
        )
    ];

    return JWT::encode($payload, $key, 'HS256');
}

// Endpoint for registering users
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

// Endpoint for authenticating users
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

// Endpoint for inserting book and author with token invalidation
$app->post('/book/add', function (Request $request, Response $response, array $args) use ($servername, $dbusername, $dbpassword, $dbname) {
    $data = json_decode($request->getBody());
    $bookTitle = $data->bookTitle;
    $authorName = $data->authorName;
    $token = $request->getHeader('Authorization')[0] ?? '';

    // Remove 'Bearer ' from token
    $token = str_replace('Bearer ', '', $token);

    try {
        // Validate the token by checking in the users table
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $dbusername, $dbpassword);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if the token exists in the users table
        $sqlCheckToken = "SELECT userid FROM users WHERE token = :token";
        $stmtCheckToken = $conn->prepare($sqlCheckToken);
        $stmtCheckToken->execute(['token' => $token]);

        if ($stmtCheckToken->rowCount() === 0) {
            return $response->withStatus(401)->write(json_encode(array("status" => "fail", "data" => array("title" => "Token is invalid or has been revoked."))));
        }

        // Decode the token after checking validity
        $decoded = JWT::decode($token, new Key('server_hack', 'HS256'));
        $userid = $decoded->data->userid;

        // Insert book
        $sqlBook = "INSERT INTO books (title) VALUES (:title)";
        $stmtBook = $conn->prepare($sqlBook);
        $stmtBook->execute(['title' => $bookTitle]);
        $bookId = $conn->lastInsertId(); // Get the last inserted book ID

        // Insert author
        $sqlAuthor = "INSERT INTO authors (name) VALUES (:name)";
        $stmtAuthor = $conn->prepare($sqlAuthor);
        $stmtAuthor->execute(['name' => $authorName]);
        $authorId = $conn->lastInsertId(); // Get the last inserted author ID

        // Call insert to books_authors
        insertBookAuthor($conn, $bookId, $authorId);

        // Generate a new token and update it in the users table
        $newToken = generateToken($userid);
        $sqlUpdateNewToken = "UPDATE users SET token = :token WHERE userid = :userid";
        $stmtUpdateNewToken = $conn->prepare($sqlUpdateNewToken);
        $stmtUpdateNewToken->execute(['userid' => $userid, 'token' => $newToken]);

        // Return the new token to the user
        $response->getBody()->write(json_encode(array("status" => "success", "Message" => "The book has been added to the collection", "newToken" => $newToken)));
    } catch (Exception $e) {
        // Handle token decoding failure or database error
        $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
    }

    return $response;
});



$app->put('/book/update', function (Request $request, Response $response, array $args) use ($servername, $dbusername, $dbpassword, $dbname) {
    $data = json_decode($request->getBody());
    $bookId = $data->bookId;
    $newBookTitle = $data->newBookTitle ?? null;
    $newAuthorName = $data->newAuthorName ?? null;
    $token = $request->getHeader('Authorization')[0] ?? '';

    // Remove 'Bearer ' from token
    $token = str_replace('Bearer ', '', $token);

    try {
        // Validate the token by checking in the users table
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $dbusername, $dbpassword);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if the token exists in the users table
        $sqlCheckToken = "SELECT userid FROM users WHERE token = :token";
        $stmtCheckToken = $conn->prepare($sqlCheckToken);
        $stmtCheckToken->execute(['token' => $token]);

        if ($stmtCheckToken->rowCount() === 0) {
            return $response->withStatus(401)->write(json_encode(array("status" => "fail", "data" => array("title" => "Token is invalid or has been revoked."))));
        }

        // Decode the token after checking validity
        $decoded = JWT::decode($token, new Key('server_hack', 'HS256'));
        $userid = $decoded->data->userid;

        // Update book title if provided
        if ($newBookTitle) {
            $sqlUpdateBook = "UPDATE books SET title = :newBookTitle WHERE bookid = :bookId";
            $stmtUpdateBook = $conn->prepare($sqlUpdateBook);
            $stmtUpdateBook->execute(['newBookTitle' => $newBookTitle, 'bookId' => $bookId]);
        }

        // Update author name if provided
        if ($newAuthorName) {
            // First, retrieve the authorid associated with the book
            $sqlGetAuthorId = "SELECT authorid FROM books_authors WHERE bookid = :bookId";
            $stmtGetAuthorId = $conn->prepare($sqlGetAuthorId);
            $stmtGetAuthorId->execute(['bookId' => $bookId]);
            $authorResult = $stmtGetAuthorId->fetch(PDO::FETCH_ASSOC);

            if ($authorResult) {
                $authorId = $authorResult['authorid'];

                // Now update the author's name
                $sqlUpdateAuthor = "UPDATE authors SET name = :newAuthorName WHERE authorid = :authorId";
                $stmtUpdateAuthor = $conn->prepare($sqlUpdateAuthor);
                $stmtUpdateAuthor->execute(['newAuthorName' => $newAuthorName, 'authorId' => $authorId]);
            } else {
                return $response->withStatus(404)->write(json_encode(array("status" => "fail", "data" => array("title" => "No author found for the specified book."))));
            }
        }

        // Generate a new token and update it in the users table
        $newToken = generateToken($userid);
        $sqlUpdateNewToken = "UPDATE users SET token = :token WHERE userid = :userid";
        $stmtUpdateNewToken = $conn->prepare($sqlUpdateNewToken);
        $stmtUpdateNewToken->execute(['userid' => $userid, 'token' => $newToken]);

        // Return success response with the new token
        $response->getBody()->write(json_encode(array("status" => "success", "Message" => "Book details updated successfully", "newToken" => $newToken)));
    } catch (Exception $e) {
        // Handle token decoding failure or database error
        $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
    }

    return $response;
});




// Book author delete endpoint with token authorization
$app->delete('/book_author/delete', function (Request $request, Response $response, array $args) use ($servername, $dbusername, $dbpassword, $dbname) {
    $data = json_decode($request->getBody());
    $collectionId = $data->collectionId;
    $token = $request->getHeader('Authorization')[0] ?? '';

    // Remove 'Bearer ' from token
    $token = str_replace('Bearer ', '', $token);

    try {
        // Validate the token by checking in the users table
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $dbusername, $dbpassword);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if the token exists in the users table
        $sqlCheckToken = "SELECT userid FROM users WHERE token = :token";
        $stmtCheckToken = $conn->prepare($sqlCheckToken);
        $stmtCheckToken->execute(['token' => $token]);

        if ($stmtCheckToken->rowCount() === 0) {
            return $response->withStatus(401)->write(json_encode(array("status" => "fail", "data" => array("title" => "Token is invalid or has been revoked."))));
        }

        // Decode the token after checking validity
        $decoded = JWT::decode($token, new Key('server_hack', 'HS256'));
        $userid = $decoded->data->userid;

        // First, get the bookid and authorid associated with the collectionId
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

            // Optionally, delete the book and author
            $sqlDeleteBook = "DELETE FROM books WHERE bookid = :bookId";
            $stmtDeleteBook = $conn->prepare($sqlDeleteBook);
            $stmtDeleteBook->execute(['bookId' => $bookId]);

            $sqlDeleteAuthor = "DELETE FROM authors WHERE authorid = :authorId";
            $stmtDeleteAuthor = $conn->prepare($sqlDeleteAuthor);
            $stmtDeleteAuthor->execute(['authorId' => $authorId]);

            // Return success response
            $response->getBody()->write(json_encode(array("status" => "success", "message" => "Entry and related books/authors deleted successfully.")));
        } else {
            $response->getBody()->write(json_encode(array("status" => "fail", "message" => "No entry found for the given collectionId.")));
        }
    } catch (PDOException $e) {
        // Handle error
        $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
    } catch (Exception $e) {
        // Handle token decoding failure or other exceptions
        $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
    }

    return $response;
});






$app->run();