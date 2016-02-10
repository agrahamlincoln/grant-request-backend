# grants-request-backend

The intent of this project is to provide a developer with a consistent api for accessing and inputting data into the database for custom web forms. Instead of completely re-writing the php back-end, the forms-api may be used with minimal modification to plug into a form front end.

### Adding a new form type

To add a new form type to the forms-api you must follow these steps.

1. Create a table structure to model your data.
2. Create a new php file inheriting db.php
    * Create methods for SELECTs, INSERTs, and UPDATES, as your project and data model requires
3. Add your new API functions into the index.php
    * Follow the same structure as used for previous form types