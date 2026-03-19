# MysqlAdmin - Lightweight MySQL Table Administration Tool

A simple, lightweight, and secure PHP class for creating web-based interfaces to administrate MySQL tables. No external dependencies required—built with native PHP and PDO for maximum compatibility and security.

## Features

- **List Records** - Display table data with pagination and filtering support
- **Add Records** - Create new records with form validation
- **Edit Records** - Inline editing with field-level control
- **Delete Records** - Remove records with confirmation options
- **Reorder Records** - Support for drag-and-drop style up/down ordering
- **Rich Text Editing** - HTML5 contenteditable editor with formatting toolbar (no TinyMCE dependency)
- **Foreign Key Support** - Automatic dropdown population from related tables
- **Field Control** - Read-only fields, excluded fields, custom labels
- **Enum/Set Support** - Automatic dropdown generation from column types
- **CSRF Protection** - Built-in CSRF token validation
- **Input Sanitization** - Complete HTML escaping and prepared statements
- **Session Security** - Consistent session handling with token validation
- **Alphanumeric Primary Keys** - Support for string and numeric primary keys
- **Debug Mode** - Optional comprehensive debugging output

## Requirements

- PHP 7.0 or higher
- PDO MySQL extension
- Modern browser with HTML5 support (for rich text editor)

## Installation

1. Copy `mysqladmin.php` to your project directory
2. Include the class in your script:

```php
require_once 'mysqladmin.php';
```

## Quick Start

```php
<?php
// Start session (required for CSRF protection)
session_start();

// Create PDO connection
$pdo = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');

// Create admin instance
$admin = new MysqlAdmin($pdo);

// Configure the table
$admin->table = 'products';
$admin->keyfield = 'id';

// Display the interface
echo $admin->display();
?>
```

## Configuration Options

### Required Properties

| Property | Type | Description |
|----------|------|-------------|
| `$table` | string | The table name to administer |
| `$keyfield` | string | The primary key column name |

### Display Options

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$showfields` | array | `['*']` | Fields to display in list (use `*` for all) |
| `$excludefields` | array | `[]` | Fields to completely hide |
| `$label` | array | `[]` | Custom field labels: `['field' => 'Label']` |
| `$prelisttext` | string | `''` | Text to display before the table |
| `$debug` | bool | `false` | Enable debug output |

### Form Options

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$readonlyfields` | array | `[]` | Fields that cannot be edited |
| `$donotadd` | array | `[]` | Fields to exclude from add form |
| `$protectedfields` | array | `[]` | Fields with protected values: `['field' => 'value']` |
| `$richtext` | array | `[]` | Fields to use rich text editor: `['description', 'content']` |

### Functionality Control

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$disableedit` | bool | `false` | Disable all editing |
| `$disableadd` | bool | `false` | Disable adding new records |
| `$nodelete` | bool | `false` | Disable delete functionality |
| `$ordering` | string | `null` | Column for ordering (enables up/down arrows) |

### Advanced Features

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$where` | string | `''` | WHERE clause for filtering records |
| `$link` | array | `[]` | Foreign key dropdowns: `['field' => 'SELECT id, name FROM table']` |
| `$append` | array | `[]` | Additional options for selects: `['field' => ['opt1', 'opt2']]` |
| `$timestamp` | string | `null` | Column to auto-update with current timestamp |
| `$goback` | string | `null` | URL to redirect after save/delete |
| `$maxentries` | int | `999999` | Max entries shown before hiding add form |

## Usage Examples

### Basic Table Admin

```php
$admin = new MysqlAdmin($pdo);
$admin->table = 'customers';
$admin->keyfield = 'cust_id';
$admin->label = [
    'cust_id' => 'Customer ID',
    'cust_name' => 'Name',
    'cust_email' => 'Email'
];
echo $admin->display();
```

### With Rich Text Editing

```php
$admin = new MysqlAdmin($pdo);
$admin->table = 'posts';
$admin->keyfield = 'post_id';
$admin->richtext = ['content', 'excerpt']; // Enable rich text for these fields
$admin->label = ['content' => 'Post Content'];
echo $admin->display();
```

### With Foreign Key Relations

```php
$admin = new MysqlAdmin($pdo);
$admin->table = 'orders';
$admin->keyfield = 'order_id';
$admin->link = [
    'customer_id' => 'SELECT cust_id, cust_name FROM customers ORDER BY cust_name',
    'status_id' => 'SELECT status_id, status_name FROM order_status'
];
echo $admin->display();
```

### Filtering Records

```php
$admin = new MysqlAdmin($pdo);
$admin->table = 'products';
$admin->keyfield = 'product_id';
$admin->where = 'active = 1 AND category_id = 5'; // Only show active products in category 5
$admin->showfields = ['product_id', 'product_name', 'price', 'stock'];
echo $admin->display();
```

### With Ordering Support

```php
$admin = new MysqlAdmin($pdo);
$admin->table = 'menu_items';
$admin->keyfield = 'item_id';
$admin->ordering = 'sort_order'; // Column to order by
// Users can now click up/down arrows to reorder items
echo $admin->display();
```

### Read-Only and Protected Fields

```php
$admin = new MysqlAdmin($pdo);
$admin->table = 'transactions';
$admin->keyfield = 'trans_id';
$admin->readonlyfields = ['trans_id', 'created_date']; // Cannot be edited
$admin->protectedfields = ['status' => 'pending']; // Always set to 'pending' on new records
echo $admin->display();
```

### Complete Advanced Example

```php
<?php
session_start();
$pdo = new PDO('mysql:host=localhost;dbname=shop', 'root', 'pass');

$admin = new MysqlAdmin($pdo);
$admin->table = 'products';
$admin->keyfield = 'id';
$admin->where = 'deleted = 0'; // Hide deleted products
$admin->showfields = ['id', 'name', 'description', 'price', 'category_id'];
$admin->label = [
    'id' => 'Product ID',
    'name' => 'Product Name',
    'description' => 'Description',
    'price' => 'Price ($)',
    'category_id' => 'Category'
];
$admin->readonlyfields = ['id'];
$admin->richtext = ['description'];
$admin->link = [
    'category_id' => 'SELECT id, name FROM categories ORDER BY name'
];
$admin->ordering = 'sort_order';
$admin->timestamp = 'updated_at';
$admin->goback = '/products/';
$admin->debug = false;

echo $admin->display();
?>
```

## Security

### Built-in Protections

- **CSRF Tokens** - All forms include CSRF token validation
- **Prepared Statements** - All database queries use PDO prepared statements to prevent SQL injection
- **Input Sanitization** - All user input is sanitized and HTML-encoded
- **Session Validation** - Edit/delete operations verify session consistency
- **Output Escaping** - All output is properly escaped to prevent XSS attacks

### Best Practices

1. Always start with `session_start()` before creating the admin instance
2. Use prepared statements and parameterized queries
3. Never expose sensitive fields via `$showfields`
4. Use `$readonlyfields` for auto-generated or immutable data
5. Set `$where` to filter records appropriately
6. Enable `$debug` only in development, never in production

## Field Types

The class automatically detects and handles these MySQL field types:

- **TEXT** - Rendered as textarea
- **INT, DOUBLE, FLOAT, DECIMAL** - Rendered as number input
- **ENUM** - Rendered as dropdown
- **SET** - Rendered as dropdown
- **Other** - Rendered as text input

Enable rich text editing with `$richtext` property for any field.

## Debug Mode

Enable debug output to troubleshoot issues:

```php
$admin->debug = true;
echo $admin->display();
```

Debug output shows:
- POST/GET data received
- Session CSRF token
- Session edit_id
- Actions detected (save, delete, add)
- CSRF validation status
- SQL query and parameters
- Number of affected rows

## Troubleshooting

### "Session not set for edit_id"
Make sure `session_start()` is called before creating the MysqlAdmin instance.

### "CSRF token validation failed"
Ensure the session is persisting between requests. Check PHP session configuration.

### "Invalid ID" with string primary keys
This is now fixed. The class supports both numeric and alphanumeric primary keys.

### Updates showing "Affected rows: 0"
- Verify the primary key value matches what's in the database
- Check that the record exists with `$admin->debug = true`
- Ensure the WHERE clause (if set) matches the record

### Rich text editor not appearing
- Confirm your browser supports HTML5 `contenteditable`
- Check that the field name is included in `$richtext` array
- Verify JavaScript is enabled in the browser

## API Reference

### Public Methods

#### `display()`
Main method that renders the interface and handles all operations.

```php
echo $admin->display();
```

#### `group($field)`
Group display by a field value (experimental).

```php
echo $admin->group('category');
```

### Automatic Features

- **Pagination** - Built into list display
- **Alternating row colors** - Automatically applied
- **Button generation** - Edit, delete, add buttons generated automatically
- **Format detection** - Field types detected and rendered appropriately

## License

MIT License - Feel free to use in your projects.

## Contributing

This is a lightweight tool designed for simplicity. For feature requests or bug reports, please open an issue.

## Changelog

### v1.0
- Initial release
- Full CRUD operations
- Rich text editor (HTML5 contenteditable)
- CSRF protection
- Support for string and numeric primary keys
- Debug mode
- Input sanitization and validation
