# Library System with JWT
A simple library system with secure access management using JSON Web Tokens (JWT). This project allows users to manage a collection of books and authors while ensuring security through token rotation, so each token is single-use only.

# Table of Contents

1. [Features](#features)
2. [Technologies Used](#technologies-used)
3. [Endpoints, Payloads, and Responses](#endpoints-payloads-responses)  
   - [Register Users](#register-users)  
   - [Authenticate Users](#authenticate-users)  
   - [Insert Books with Author](#insert-books-with-author)  
   - [Update Books with Author](#update-books-with-author)  
   - [Display Books with Author](#display-books-with-author)  
   - [Delete Books and Authors](#delete-books-and-authors)
4. [How to Use](#how-to-use)


## Features
1. **User Registration:** Create an account to access the library system.
2. **Token-Based Authentication:** Authenticate to obtain a unique JWT for secure database operations.
3. **Token Rotation:** Each JWT is single-use and replaced after an action, preventing token reuse.
4. **CRUD Operations:**
	- Insert: Add new books and authors to the database.
	- Update: Modify existing records.
	- Delete: Remove books and authors.
	- Retrieve: Access information on stored books and authors.

## Technologies Used
1. Backend: PHP (with Slim Framework)
2. Database: MySQL
3. Authentication: JSON Web Tokens (JWT) with token rotation

# Endpoints Payloads Response
## Register Users
**Endpoint: POST** `/gabas_library/public/user/register`<br>
**Payload:**
```
{
  "username":"admin123",
  "password":"admin123"
}
```
**Response:**
```
{
  "status": "success",
  "data": null
}
```
## Authenticate Users
**Endpoint: POST** `/gabas_library/public/user/authenticate`<br>
**Payload:**
```
{
  "username":"admin123",
  "password":"admin123"
}
```
**Response:**
```
{
  "status": "success",
  "token": "<generated-token>",
  "data": null
}
```
## Insert Books with Author
**Endpoint: POST** `/gabas_library/public/book/add`<br>
**Payload:**
```
{
    "bookTitle": "sample bookname",
    "authorName": "sample authorname"
}
```
**Response:**
```
{
  "status": "success",
  "Message": "The book has been added to the collection",
  "newToken": "<generated-token>"
}
```
## Update Books with Author
**Endpoint: PUT** `/gabas_library/public/book/update`<br>
**Payload:**
```
{
    "bookId": 9,
    "newBookTitle": "Update Bookname",
    "newAuthorName": "Update AuthorName"
}

```
**Response:**
```
{
  "status": "success",
  "Message": "The book has been updated",
  "newToken": "<generated-token>"
}
```
## Display Books with Author
**Endpoint: GET** `/gabas_library/public/book/collection`<br>
**Payload:**
```
{
    "collectionId": "16"
}
```
**Response:**
```
{
  "status": "success",
  "data": [
    {
      "bookid": 16,
      "book_title": "sample bookname",
      "authorid": 16,
      "author_name": "sample authorname"
    }
  ],
  "newToken": "<generated-token>"
}
```
## Delete Books and Authors
**Endpoint: DEL** `/gabas_library/public/book/delete`<br>
**Payload:**
```
{
    "collectionId": 16
}
```
**Response:**
```
{
  "status": "success",
  "message": "Entry and related book/author deleted successfully.",
  "newToken": "<generated-token>"
}
```

# How to Use
1. First, ensure that the `gabas_library.sql` database is imported into your MySQL database.
    - This can be found in the `gabas_library/database/gabas_library.sql` file.
2. Create an account using the **Register** payload, then authenticate with the **Authenticate** payload.
3. To use the **Insert, Update, Display, and Delete Books** payloads, copy the `<generated-token>` received from authentication:
    - Go to the **Headers** section in your Postman or Thunderclient.
    - Add **Authorization** in the header and paste the `<generated-token>` in the value field next to **Authorization**.
4. Use the payloads, configure them according to your needs, and press **SEND**.
