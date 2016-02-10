Routes
------

### GET /docs
You may select a document from the following list:

* /routes
* /

Do so by using the route GET /docs/document. e.g. GET /docs/routes will return the document pertaining to routes.

### POST /register
* **Requires:** JSON object with the following content
```
{
  'email': 'XXXX',
  'name': 'XXXX'
}
```

* **Returns:** JSON object with the following content
```
{
  'success': 'true/false',
  'message': 'only when Success = False',
  'jwt': 'only when Success = True'
}
```

The JWT issued by the server activates 10 seconds after creation, and expires 10 minutes after activation.

### POST /submit/request/grant
* **Requires Authentication**
* Requires JSON Object with the following content
```
{
  'json': 'need to put the request format here'
}
```

### Grant Funding Routes
* **Requires Authentication**

**Implemented Routes**
* **GET /grant-funding/42** - Retrieves Grant Funding request #42
* **POST /grant-funding** - Submit a Grant Funding request

**To be implemented**
* **GET /grant-funding** - Retrieves a list of Grant Funding requests
* ***PUT /grant-funding/42** - Update Grant Funding request #42*
* ***DELETE /grant-funding/42** - Delete Grant Funding request #42*
