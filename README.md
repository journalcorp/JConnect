# Web Portal Documentation

## Project Overview
This web portal is designed for internal use within an organization. It provides a simple and user-friendly interface for employees to log in and access their information.

## Features
- **Login Page**: Secure login for users with username "admin" and password "admin".
- **Homepage**: Displays employee details including name, surname, position, and department.
- **Collapsible Sidebar Menu**: Easy navigation throughout the portal.

## Project Structure
```
web-portal
├── assets
│   ├── css
│   │   └── styles.css
│   ├── js
│   │   └── scripts.js
│   └── bootstrap
│       ├── css
│       └── js
├── includes
│   ├── header.php
│   ├── footer.php
│   └── sidebar.php
├── pages
│   ├── login.php
│   └── home.php
├── index.php
├── config.php
└── README.md
```

## Setup Instructions
1. **Clone the Repository**: Clone this repository to your local machine.
2. **Install Dependencies**: Ensure you have a web server (like Apache) and PHP installed.
3. **Configure Database**: Update the `config.php` file with your database connection details if needed.
4. **Access the Portal**: Open your web browser and navigate to `http://localhost/web-portal/index.php`.

## Usage Guidelines
- Use the credentials:
  - **Username**: admin
  - **Password**: admin
- After logging in, you will be redirected to the homepage where you can view your details.

## Contributing
Feel free to fork the repository and submit pull requests for any improvements or bug fixes.

## License
This project is licensed under the MIT License. See the LICENSE file for more details.