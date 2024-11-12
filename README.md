# Library System with JWT
A simple library system with secure access management using JSON Web Tokens (JWT). This project allows users to manage a collection of books and authors while ensuring security through token rotation, so each token is single-use only.
### Features
1. **User Registration:** Create an account to access the library system.
2. **Token-Based Authentication:** Authenticate to obtain a unique JWT for secure database operations.
3. **Token Rotation:** Each JWT is single-use and replaced after an action, preventing token reuse.
4. **CRUD Operations:**
	- Insert: Add new books and authors to the database.
	- Update: Modify existing records.
	- Delete: Remove books and authors.
	- Retrieve: Access information on stored books and authors.

### Technologies Used
1. Backend: PHP (with Slim Framework)
2. Database: MySQL
3. Authentication: JSON Web Tokens (JWT) with token rotation

### Endpoints
#### Register
**Payload:** `gabas_library/public/user/register`
```{
  "username":"admin123",
  "password":"admin123"
}
```

