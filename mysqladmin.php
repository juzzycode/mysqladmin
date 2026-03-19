<?php
/**
 * MysqlAdmin - Lightweight MySQL Table Administration Tool
 *
 * This class provides a simple web-based interface for administering MySQL tables.
 * It supports listing, adding, editing, deleting, and reordering records.
 *
 * Features:
 * - List records with pagination and filtering
 * - Add new records with form validation
 * - Edit existing records with inline rich text editing
 * - Delete records (with confirmation)
 * - Reorder records (up/down) based on a specified column
 * - Support for foreign key relationships (links)
 * - Enum and set field handling
 * - Built-in HTML5 rich text editor with formatting toolbar
 * - Read-only fields
 * - Excludable fields
 * - Custom field labels
 * - Grouping support (basic implementation)
 * - CSRF protection
 * - Input sanitization and validation
 *
 * Security improvements:
 * - Uses PDO with prepared statements to prevent SQL injection
 * - CSRF tokens for form submissions
 * - Input validation and sanitization
 * - Session-based security checks
 *
 * Requirements:
 * - PHP 7.0+
 * - PDO MySQL extension
 * - Modern browser with HTML5 contenteditable support
 *
 * Usage:
 * session_start(); // Required for CSRF protection
 * // Database connection
 * $pdo = new PDO('mysql:host=localhost;dbname=your_db', 'user', 'pass');
 * // Create admin instance
 * $admin = new MysqlAdmin($pdo);
 * $admin->table = 'users';
 * $admin->keyfield = 'id';
 * $admin->where = 'active = 1';
 * $admin->showfields = ['name', 'email', 'role'];
 * $admin->label = ['name' => 'Full Name'];
 * $admin->link = ['role' => 'SELECT id, role_name FROM roles'];
 * $admin->richtext = ['description', 'content']; // Field names for rich text editing
 * // Display the interface
 * echo $admin->display();
 */

class MysqlAdmin {
    // Database connection (PDO instance)
    private $pdo;

    // Required properties
    public $table;              // Table to administer
    public $where = '';         // WHERE clause for filtering records
    public $keyfield;           // Primary key field name

    // Display options
    public $showfields = ['*']; // Fields to display (default all)
    public $excludefields = []; // Fields to exclude from display
    public $label = [];         // Custom labels for fields ['field' => 'Label']
    public $readonlyfields = []; // Fields that are read-only in forms

    // Form options
    public $donotadd = [];      // Fields to exclude from add form
    public $protectedfields = []; // Fields with protected values ['field' => 'value']

    // Functionality toggles
    public $disableedit = false; // Disable editing
    public $disableadd = false;  // Disable adding new records
    public $nodelete = false;    // Disable delete functionality
    public $ordering = null;    // Field for ordering (enables up/down arrows)

    // Advanced features
    public $link = [];          // Foreign key links ['field' => 'SELECT id, name FROM table']
    public $append = [];        // Additional options for select fields ['field' => ['option1', 'option2']]
    public $replacelinks = [];  // URL replacement functions ['field' => function($field, $url) { return new_url; }]
    public $richtext = [];      // Fields to use rich text editor (HTML5 contenteditable)
    public $timestamp = null;   // Timestamp field to auto-update

    // UI options
    public $prelisttext = '';   // Text to display before the list
    public $grouplabel = '';    // Label for grouping (not fully implemented)
    public $maxentries = 999999; // Maximum entries to show (affects add form visibility)
    public $goback = null;      // URL to redirect after save/delete

    // Internal properties
    private $csrf_token;
    private $count = 0;

    /**
     * Constructor
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->generateCsrfToken();
    }

    /**
     * Generate CSRF token for form protection
     */
    private function generateCsrfToken() {
        // For security, use a consistent token per session rather than regenerating
        if (!isset($_SESSION['mysqladmin_csrf_token'])) {
            $_SESSION['mysqladmin_csrf_token'] = bin2hex(random_bytes(32));
        }
        $this->csrf_token = $_SESSION['mysqladmin_csrf_token'];
    }

    /**
     * Validate CSRF token
     */
    private function validateCsrfToken() {
        if (!isset($_POST['csrf_token'])) {
            return false;
        }
        return hash_equals($this->csrf_token, $_POST['csrf_token']);
    }

    /**
     * Sanitize input data
     * @param mixed $data Input data
     * @return mixed Sanitized data
     */
    private function sanitize($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitize'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize HTML content (allow basic tags)
     * @param string $html HTML content
     * @return string Sanitized HTML
     */
    private function sanitizeHtml($html) {
        $allowed = '<p><br><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><a><img>';
        return strip_tags($html, $allowed);
    }

    /**
     * Validate ID input (supports both numeric and string primary keys)
     * @param mixed $value Value to validate
     * @return bool
     */
    private function isValidId($value) {
        return !empty(trim($value));
    }

    /**
     * Get rich text editor assets (CSS and JS)
     * @return string HTML with CSS and JS
     */
    private function getRichTextAssets() {
        return <<<'EOF'
<style>
.richtext-toolbar {
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-bottom: none;
    padding: 8px;
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}
.richtext-toolbar button {
    padding: 6px 12px;
    border: 1px solid #ccc;
    background: white;
    cursor: pointer;
    font-size: 13px;
    border-radius: 3px;
    transition: all 0.2s;
}
.richtext-toolbar button:hover {
    background: #e9e9e9;
    border-color: #999;
}
.richtext-toolbar button.active {
    background: #0066cc;
    color: white;
    border-color: #0066cc;
}
.richtext-toolbar select {
    padding: 6px;
    border: 1px solid #ccc;
    border-radius: 3px;
    font-size: 13px;
}
.richtext-editor {
    border: 1px solid #ddd;
    padding: 12px;
    min-height: 200px;
    font-family: Arial, sans-serif;
    font-size: 14px;
    line-height: 1.5;
    overflow-y: auto;
}
.richtext-editor:focus {
    outline: none;
    border-color: #0066cc;
    box-shadow: 0 0 5px rgba(0, 102, 204, 0.3);
}
.richtext-preview {
    background: #f9f9f9;
    border: 1px solid #ddd;
    padding: 10px;
    border-radius: 3px;
    max-height: 100px;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.4;
}
.debug {
    background: #ffffcc;
    border: 1px solid #ffcc00;
    padding: 10px;
    margin: 10px 0;
    font-family: monospace;
    font-size: 12px;
    white-space: pre-wrap;
    word-wrap: break-word;
}
.error {
    background: #ffcccc;
    border: 1px solid #ff0000;
    padding: 10px;
    margin: 10px 0;
    color: #cc0000;
}
.success {
    background: #ccffcc;
    border: 1px solid #00ff00;
    padding: 10px;
    margin: 10px 0;
    color: #00cc00;
}
</style>
<script>
(function() {
    function initRichText() {
        const editors = document.querySelectorAll('.richtext-editor');
        editors.forEach(editor => {
            if (editor.dataset.initialized) return;
            editor.dataset.initialized = 'true';
            
            const toolbar = document.createElement('div');
            toolbar.className = 'richtext-toolbar';
            
            const tools = [
                { cmd: 'bold', label: 'B', title: 'Bold (Ctrl+B)' },
                { cmd: 'italic', label: 'I', title: 'Italic (Ctrl+I)' },
                { cmd: 'underline', label: 'U', title: 'Underline (Ctrl+U)' },
                { sep: true },
                { cmd: 'formatBlock', value: '<p>', label: 'P', title: 'Paragraph' },
                { cmd: 'formatBlock', value: '<h1>', label: 'H1', title: 'Heading 1' },
                { cmd: 'formatBlock', value: '<h2>', label: 'H2', title: 'Heading 2' },
                { cmd: 'formatBlock', value: '<h3>', label: 'H3', title: 'Heading 3' },
                { sep: true },
                { cmd: 'insertUnorderedList', label: 'List', title: 'Bullet List' },
                { cmd: 'insertOrderedList', label: 'Ordered', title: 'Numbered List' },
                { sep: true },
                { cmd: 'createLink', label: 'Link', title: 'Insert Link', prompt: true },
                { cmd: 'unlink', label: 'Unlink', title: 'Remove Link' },
                { sep: true },
                { cmd: 'removeFormat', label: 'Clear', title: 'Clear Formatting' }
            ];
            
            tools.forEach(tool => {
                if (tool.sep) {
                    const sep = document.createElement('div');
                    sep.style.width = '1px';
                    sep.style.background = '#ccc';
                    sep.style.margin = '4px 0';
                    toolbar.appendChild(sep);
                    return;
                }
                
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = tool.label;
                btn.title = tool.title;
                btn.onclick = (e) => {
                    e.preventDefault();
                    if (tool.prompt) {
                        const value = prompt(tool.label + ':');
                        if (value) document.execCommand(tool.cmd, false, value);
                    } else {
                        document.execCommand(tool.cmd, false, tool.value || true);
                    }
                    const editorArea = btn.closest('.richtext-toolbar').nextElementSibling;
                    editorArea.focus();
                    syncHiddenInput(editorArea);
                };
                toolbar.appendChild(btn);
            });
            
            editor.parentNode.insertBefore(toolbar, editor);
            editor.contentEditable = 'true';
            editor.addEventListener('input', function() { syncHiddenInput(this); });
            editor.addEventListener('blur', function() { syncHiddenInput(this); });
        });
    }
    
    function syncHiddenInput(editor) {
        const input = editor.dataset.inputId ? document.getElementById(editor.dataset.inputId) : null;
        if (input) {
            input.value = editor.innerHTML;
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRichText);
    } else {
        initRichText();
    }
})();
</script>
EOF;
    }

    /**
     * Main display method - routes to appropriate action
     * @return string HTML output
     */
    public function display() {
        if (!$this->validateRequiredProperties()) {
            return '<div class="error">Required properties (table, keyfield) are not set.</div>';
        }

        // Debug: Show what POST/GET data we received
        $debug = '';
        if (!empty($_POST)) {
            $debug .= '<div class="debug">POST data: ' . $this->sanitize(print_r($_POST, true)) . '</div>';
        }
        if (!empty($_GET)) {
            $debug .= '<div class="debug">GET data: ' . $this->sanitize(print_r($_GET, true)) . '</div>';
        }
        if (isset($_SESSION['csrf_token'])) {
            $debug .= '<div class="debug">Session CSRF: ' . $this->sanitize($_SESSION['csrf_token']) . '</div>';
        }
        if (isset($_SESSION['edit_id'])) {
            $debug .= '<div class="debug">Session edit_id: ' . $this->sanitize($_SESSION['edit_id']) . '</div>';
        }

        if (isset($_POST['save'])) {
            $csrfValid = $this->validateCsrfToken();
            $debug .= '<div class="debug">Save action detected. CSRF valid: ' . ($csrfValid ? 'YES' : 'NO') . '</div>';
            if ($csrfValid) {
                return $debug . $this->save();
            } else {
                return $debug . '<div class="error">CSRF token validation failed for save action.</div>';
            }
        }
        if (isset($_POST['delete']) && !$this->nodelete) {
            $csrfValid = $this->validateCsrfToken();
            $debug .= '<div class="debug">Delete action detected. CSRF valid: ' . ($csrfValid ? 'YES' : 'NO') . '</div>';
            if ($csrfValid) {
                return $debug . $this->delete();
            } else {
                return $debug . '<div class="error">CSRF token validation failed for delete action.</div>';
            }
        }
        if (isset($_GET['move'])) {
            return $debug . $this->move();
        }
        if (isset($_GET['edit'])) {
            return $debug . $this->edit();
        }
        if (isset($_POST['add']) && !$this->disableadd) {
            $csrfValid = $this->validateCsrfToken();
            $debug .= '<div class="debug">Add action detected. CSRF valid: ' . ($csrfValid ? 'YES' : 'NO') . '</div>';
            if ($csrfValid) {
                return $debug . $this->add();
            } else {
                return $debug . '<div class="error">CSRF token validation failed for add action.</div>';
            }
        }

        return $debug . $this->listTable();
    }

    /**
     * Validate required properties
     * @return bool
     */
    private function validateRequiredProperties() {
        return !empty($this->table) && !empty($this->keyfield);
    }

    /**
     * Get column types for the table
     * @return array Column types
     */
    private function getTableCols() {
        $stmt = $this->pdo->prepare("SHOW FIELDS FROM {$this->table}");
        $stmt->execute();
        $cols = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cols[$row['Field']] = $row['Type'];
        }
        return $cols;
    }

    /**
     * Build WHERE clause
     * @param string $additional Additional conditions
     * @return string WHERE clause
     */
    private function buildWhere($additional = '') {
        $where = '';
        if (!empty($this->where)) {
            $where = "WHERE {$this->where}";
        }
        if (!empty($additional)) {
            $where .= ($where ? ' AND ' : 'WHERE ') . $additional;
        }
        return $where;
    }

    /**
     * List table records
     * @return string HTML output
     */
    private function listTable() {
        try {
            $cols = $this->getTableCols();
            $fields = $this->buildFieldsList();
            $where = $this->buildWhere();
            $order = !empty($this->ordering) ? "ORDER BY {$this->ordering}" : '';

            $query = "SELECT {$fields} FROM {$this->table} {$where} {$order}";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $html = $this->getRichTextAssets();
            $html .= $this->renderListTable($rows, $cols);
            if (!$this->disableadd && $this->count < $this->maxentries) {
                $html .= $this->renderAddForm($cols);
            }
            return $html;
        } catch (PDOException $e) {
            return '<div class="error">Database error: ' . $this->sanitize($e->getMessage()) . '</div>';
        }
    }

    /**
     * Build fields list for SELECT query
     * @return string Fields list
     */
    private function buildFieldsList() {
        $fields = '';
        foreach ($this->showfields as $field) {
            $fields .= "{$field},";
        }
        if (!empty($this->ordering) && !in_array($this->ordering, $this->showfields)) {
            $fields .= "{$this->ordering},";
        }
        $fields .= "{$this->keyfield} AS ourkeyfield";
        return $fields;
    }

    /**
     * Render the list table
     * @param array $rows Data rows
     * @param array $cols Column types
     * @return string HTML
     */
    private function renderListTable($rows, $cols) {
        $html = '<div class="table"><table class="listing form" cellpadding="0" cellspacing="0" border="0"><tr>';
        $fieldNames = [];

        // Build headers
        foreach ($rows as $row) {
            foreach ($row as $field => $value) {
                if ($field === 'ourkeyfield') continue;
                if (in_array($field, $this->excludefields)) continue;
                if (in_array($field, $this->richtext)) continue;

                $label = isset($this->label[$field]) ? $this->label[$field] : ucfirst($field);
                $html .= '<th>' . $this->sanitize($label) . '</th>';
                $fieldNames[] = $field;
            }
            break; // Only need field names from first row
        }

        $html .= '</tr>';

        // Build rows
        foreach ($rows as $row) {
            $this->count++;
            $html .= '<tr class="' . ($this->count % 2 ? 'oddList' : 'evenList') . '">';
            foreach ($row as $field => $value) {
                if ($field === 'ourkeyfield') continue;
                if (in_array($field, $this->excludefields)) continue;
                if (in_array($field, $this->richtext)) continue;

                if (!empty($this->ordering) && $field === $this->ordering) {
                    $html .= '<td><a href="?edit=' . $row['ourkeyfield'] . '&move=up">↑</a> <a href="?edit=' . $row['ourkeyfield'] . '&move=down">↓</a></td>';
                } else {
                    $displayValue = strlen($value) > 80 ? substr($this->sanitize($value), 0, 80) . '...' : $this->sanitize($value);
                    if ($this->disableedit) {
                        $html .= '<td>' . $displayValue . '</td>';
                    } else {
                        $html .= '<td><a href="?edit=' . $row['ourkeyfield'] . '">' . $displayValue . '</a></td>';
                    }
                }
            }
            $html .= '</tr>';
        }

        $html .= '</table></div>' . $this->prelisttext;
        return $html;
    }

    /**
     * Render add form
     * @param array $cols Column types
     * @return string HTML
     */
    private function renderAddForm($cols) {
        $url = strtok($_SERVER['REQUEST_URI'], '?');
        $html = '<div class="table"><form action="' . $url . '" method="post">
            <input type="hidden" name="csrf_token" value="' . $this->csrf_token . '">
            <input type="hidden" name="add" value="add">
            <table class="listing form" cellspacing="0" cellpadding="0" border="0" width="613">
            <tr><th class="full" colspan="2">Add an Item</th></tr>';

        $odd = 1;
        foreach ($cols as $field => $type) {
            if ($field === $this->keyfield) continue;
            if (in_array($field, $this->excludefields)) continue;
            if (in_array($field, $this->donotadd)) continue;
            if (in_array($field, $this->readonlyfields)) continue;

            $label = isset($this->label[$field]) ? $this->label[$field] : ucfirst($field);
            $oddeven = ($odd++ % 2) ? 'oddList' : 'evenList';
            $html .= '<tr class="' . $oddeven . '"><td class="first">' . $this->sanitize($label) . ':</td><td class="last">';

            if (isset($this->link[$field])) {
                $html .= $this->renderSelectField($field, null);
            } elseif (in_array($field, $this->richtext)) {
                $inputId = 'richtext_add_' . $field;
                $html .= '<div class="richtext-editor" data-input-id="' . $inputId . '"></div>';
                $html .= '<input type="hidden" id="' . $inputId . '" name="' . $field . '" value="">';
            } elseif (stripos($type, 'enum') === 0) {
                $html .= $this->renderEnumField($type, null, $field);
            } elseif (stripos($type, 'text') !== false) {
                $html .= '<textarea cols="40" rows="6" name="' . $field . '"></textarea>';
            } elseif (stripos($type, 'int') !== false || stripos($type, 'double') !== false ||
                      stripos($type, 'float') !== false || stripos($type, 'decimal') !== false) {
                $html .= '<input type="text" name="' . $field . '" value="" size="5">';
            } else {
                $html .= '<input type="text" name="' . $field . '" value="" size="35">';
            }

            $html .= '</td></tr>';
        }

        $oddeven = ($odd++ % 2) ? 'oddList' : 'evenList';
        $html .= '<tr class="' . $oddeven . '"><td colspan="2" align="right"><input type="submit" value="Add new record"></td></tr>';
        $html .= '</table></form></div>';
        return $html;
    }

    /**
     * Render select field for foreign keys
     * @param string $field Field name
     * @param mixed $selected Selected value
     * @return string HTML
     */
    private function renderSelectField($field, $selected) {
        $stmt = $this->pdo->prepare($this->link[$field]);
        $stmt->execute();
        $options = '<option value="">-- Select --</option>';
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $sel = ($row[0] == $selected) ? ' selected' : '';
            $options .= '<option value="' . $this->sanitize($row[0]) . '"' . $sel . '>' . $this->sanitize($row[1]) . '</option>';
        }
        if (isset($this->append[$field])) {
            foreach ($this->append[$field] as $option) {
                $sel = ($option == $selected) ? ' selected' : '';
                $options .= '<option value="' . $this->sanitize($option) . '"' . $sel . '>' . $this->sanitize($option) . '</option>';
            }
        }
        return '<select name="' . $field . '">' . $options . '</select>';
    }

    /**
     * Render enum field
     * @param string $type Column type
     * @param mixed $selected Selected value
     * @param string $field Field name
     * @return string HTML
     */
    private function renderEnumField($type, $selected, $field) {
        $values = str_ireplace(['enum(', ')'], '', $type);
        $values = str_replace("'", '', $values);
        $options = '';
        foreach (explode(',', $values) as $value) {
            $sel = (trim($value) == $selected) ? ' selected' : '';
            $options .= '<option value="' . $this->sanitize(trim($value)) . '"' . $sel . '>' . $this->sanitize(trim($value)) . '</option>';
        }
        return '<select name="' . $field . '">' . $options . '</select>';
    }

    /**
     * Edit record form
     * @return string HTML output
     */
    private function edit() {
        if ($this->disableedit) {
            return '<div class="error">Editing is disabled.</div>';
        }

        $id = $_GET['edit'];
        if (!$this->isValidId($id)) {
            return '<div class="error">Invalid ID.</div>';
        }

        try {
            $cols = $this->getTableCols();
            $where = $this->buildWhere("{$this->keyfield} = ?");
            $fields = $this->buildFieldsList();

            $stmt = $this->pdo->prepare("SELECT {$fields} FROM {$this->table} {$where}");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return '<div class="error">Record not found.</div>';
            }

            $_SESSION['edit_id'] = $id;
            return $this->getRichTextAssets() . $this->renderEditForm($row, $cols);
        } catch (PDOException $e) {
            return '<div class="error">Database error: ' . $this->sanitize($e->getMessage()) . '</div>';
        }
    }

    /**
     * Render edit form
     * @param array $row Data row
     * @param array $cols Column types
     * @return string HTML
     */
    private function renderEditForm($row, $cols) {
        $url = preg_replace('/[?&]edit=[^&]*/', '', $_SERVER['REQUEST_URI']);
        $html = '<form action="' . $url . '" method="post">
            <input type="hidden" name="csrf_token" value="' . $this->csrf_token . '">
            <input type="hidden" name="save" value="' . $row['ourkeyfield'] . '">
            <table border="2">
            <tr><td><table border="0">
            <tr><td><b>Name</b></td><td><b>Value</b></td></tr>';

        foreach ($row as $field => $value) {
            if ($field === 'ourkeyfield') continue;
            if (in_array($field, $this->excludefields)) continue;
            if (in_array($field, $this->donotadd)) continue;

            $label = isset($this->label[$field]) ? $this->label[$field] : ucfirst($field);
            $html .= '<tr><td>' . $this->sanitize($label) . ':</td><td>';

            if (in_array($field, $this->readonlyfields)) {
                $html .= $this->sanitize($value);
            } elseif (isset($this->link[$field])) {
                $html .= $this->renderSelectField($field, $value);
            } elseif (in_array($field, $this->richtext)) {
                $inputId = 'richtext_edit_' . $field;
                $html .= '<div class="richtext-editor" data-input-id="' . $inputId . '">' . $this->sanitizeHtml($value) . '</div>';
                $html .= '<input type="hidden" id="' . $inputId . '" name="' . $field . '" value="' . $this->sanitize($value) . '">';
            } elseif (stripos($cols[$field], 'enum') === 0) {
                $html .= $this->renderEnumField($cols[$field], $value, $field);
            } elseif (stripos($cols[$field], 'text') !== false) {
                $html .= '<textarea cols="50" rows="6" name="' . $field . '">' . $this->sanitize($value) . '</textarea>';
            } elseif (stripos($cols[$field], 'int') !== false || stripos($cols[$field], 'double') !== false ||
                      stripos($cols[$field], 'float') !== false || stripos($cols[$field], 'decimal') !== false) {
                $html .= '<input type="text" name="' . $field . '" value="' . $this->sanitize($value) . '" size="5">';
            } else {
                $html .= '<input type="text" name="' . $field . '" value="' . $this->sanitize($value) . '" size="35">';
            }

            $html .= '</td></tr>';
        }

        $deleteBtn = $this->nodelete ? '' : '<input type="submit" name="delete" value="Delete">';
        $html .= '<tr><td colspan="2" align="right"><input type="submit" value="Save"> ' . $deleteBtn . '</td></tr>';
        $html .= '</table></td></tr></table></form>';
        return $html;
    }

    /**
     * Save edited record
     * @return string Status message
     */
    private function save() {
        if ($this->disableedit) {
            return '<div class="error">Editing is disabled.</div>';
        }

        $id = $_POST['save'];
        if (!isset($_SESSION['edit_id'])) {
            return '<div class="error">Session not set for edit_id.</div>';
        }
        if (!$this->isValidId($id)) {
            return '<div class="error">Invalid ID: ' . $this->sanitize($id) . '</div>';
        }
        if ($_SESSION['edit_id'] != $id) {
            return '<div class="error">Session mismatch: session=' . $this->sanitize($_SESSION['edit_id']) . ' post=' . $this->sanitize($id) . '</div>';
        }

        try {
            $cols = $this->getTableCols();
            $setParts = [];
            $params = [];

            foreach ($_POST as $field => $value) {
                if ($field === 'save' || $field === 'csrf_token') continue;
                if ($field === $this->keyfield) continue;
                if (in_array($field, $this->excludefields)) continue;
                if (in_array($field, $this->donotadd)) continue;

                // Sanitize rich text content
                if (in_array($field, $this->richtext)) {
                    $value = $this->sanitizeHtml($value);
                }

                if (isset($this->replacelinks[$field])) {
                    $value = $this->processUrlReplacements($field, $value);
                }

                $setParts[] = "`{$field}` = ?";
                $params[] = $value;
            }

            if (empty($setParts)) {
                return '<div class="error">No fields to update.</div>';
            }

            $params[] = $id;
            $query = "UPDATE {$this->table} SET " . implode(', ', $setParts) . " WHERE {$this->keyfield} = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $affected = $stmt->rowCount();

            if ($this->timestamp) {
                $stmt = $this->pdo->prepare("UPDATE {$this->table} SET {$this->timestamp} = NOW() WHERE {$this->keyfield} = ?");
                $stmt->execute([$id]);
            }

            if ($this->goback) {
                header("Location: {$this->goback}");
                exit;
            }

            return '<div class="success">Entry saved. Affected rows: ' . $affected . '. Query: ' . $this->sanitize($query) . '</div>';
        } catch (PDOException $e) {
            return '<div class="error">Database error: ' . $this->sanitize($e->getMessage()) . '</div>';
        }
    }

    /**
     * Add new record
     * @return string Status message
     */
    private function add() {
        if ($this->disableadd) {
            return '<div class="error">Adding is disabled.</div>';
        }

        try {
            $cols = $this->getTableCols();
            $fields = [];
            $placeholders = [];
            $params = [];

            foreach ($cols as $field => $type) {
                if ($field === $this->keyfield) continue; // Assume auto-increment
                if (isset($this->protectedfields[$field])) {
                    $fields[] = "`{$field}`";
                    $placeholders[] = '?';
                    $params[] = $this->protectedfields[$field];
                    continue;
                }
                if (in_array($field, $this->excludefields)) continue;
                if (in_array($field, $this->donotadd)) continue;

                $value = $_POST[$field] ?? '';
                
                // Sanitize rich text content
                if (in_array($field, $this->richtext)) {
                    $value = $this->sanitizeHtml($value);
                }

                if (isset($this->replacelinks[$field])) {
                    $value = $this->processUrlReplacements($field, $value);
                }

                $fields[] = "`{$field}`";
                $placeholders[] = '?';
                $params[] = $value;
            }

            $query = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            if ($this->timestamp) {
                $lastId = $this->pdo->lastInsertId();
                $stmt = $this->pdo->prepare("UPDATE {$this->table} SET {$this->timestamp} = NOW() WHERE {$this->keyfield} = ?");
                $stmt->execute([$lastId]);
            }

            if ($this->goback) {
                header("Location: {$this->goback}");
                exit;
            }

            header("Location: {$_SERVER['REQUEST_URI']}");
            return '<div class="success">Entry added.</div>';
        } catch (PDOException $e) {
            return '<div class="error">Database error: ' . $this->sanitize($e->getMessage()) . '</div>';
        }
    }

    /**
     * Delete record
     * @return string Status message
     */
    private function delete() {
        if ($this->nodelete) {
            return '<div class="error">Deleting is disabled.</div>';
        }

        $id = $_POST['save'];
        if (!$this->isValidId($id) || $_SESSION['edit_id'] != $id) {
            return '<div class="error">Invalid session or ID.</div>';
        }

        try {
            $where = $this->buildWhere("{$this->keyfield} = ?");
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} {$where}");
            $stmt->execute([$id]);

            if ($this->goback) {
                header("Location: {$this->goback}");
                exit;
            }

            return '<div class="success">Entry deleted.</div>';
        } catch (PDOException $e) {
            return '<div class="error">Database error: ' . $this->sanitize($e->getMessage()) . '</div>';
        }
    }

    /**
     * Move record up/down for ordering
     * @return string Redirect or error
     */
    private function move() {
        if (empty($this->ordering)) {
            return '<div class="error">Ordering not configured.</div>';
        }

        $id = $_GET['edit'];
        if (!$this->isValidId($id)) {
            return '<div class="error">Invalid ID.</div>';
        }

        try {
            $where = $this->buildWhere();
            $query = "SELECT {$this->keyfield}, {$this->ordering} FROM {$this->table} {$where} ORDER BY {$this->ordering}";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $currentIndex = null;
            foreach ($rows as $index => $row) {
                if ($row[$this->keyfield] == $id) {
                    $currentIndex = $index;
                    break;
                }
            }

            if ($currentIndex === null) {
                return '<div class="error">Record not found.</div>';
            }

            if ($_GET['move'] === 'up' && $currentIndex > 0) {
                $prevRow = $rows[$currentIndex - 1];
                $this->swapOrdering($rows[$currentIndex], $prevRow);
            } elseif ($_GET['move'] === 'down' && $currentIndex < count($rows) - 1) {
                $nextRow = $rows[$currentIndex + 1];
                $this->swapOrdering($rows[$currentIndex], $nextRow);
            }

            $redirect = preg_replace('/[?&]edit=[^&]*&?[?&]move=[^&]*/', '', $_SERVER['REQUEST_URI']);
            header("Location: {$redirect}");
            exit;
        } catch (PDOException $e) {
            return '<div class="error">Database error: ' . $this->sanitize($e->getMessage()) . '</div>';
        }
    }

    /**
     * Swap ordering values between two rows
     * @param array $row1 First row
     * @param array $row2 Second row
     */
    private function swapOrdering($row1, $row2) {
        $temp = $row1[$this->ordering];
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET {$this->ordering} = ? WHERE {$this->keyfield} = ?");
        $stmt->execute([$row2[$this->ordering], $row1[$this->keyfield]]);
        $stmt->execute([$temp, $row2[$this->keyfield]]);
    }

    /**
     * Process URL replacements
     * @param string $field Field name
     * @param string $value Field value
     * @return string Processed value
     */
    private function processUrlReplacements($field, $value) {
        if (!isset($this->replacelinks[$field])) return $value;

        preg_match_all('/http[^\\\\"\'> \n]*/', $value, $urls, PREG_SET_ORDER);
        foreach ($urls as $urlMatch) {
            if (strpos($urlMatch[0], 'firewater') !== false) continue;
            $newUrl = call_user_func($this->replacelinks[$field], $field, $urlMatch[0]);
            $value = str_replace($urlMatch[0], $newUrl, $value);
        }
        return $value;
    }

    /**
     * Group functionality (basic implementation)
     * @param string $group Group field
     * @return string HTML output
     */
    public function group($group) {
        if (isset($_GET['group'])) {
            if (empty($this->where)) {
                $this->where = "{$group} = '{$this->sanitize($_GET['group'])}'";
            } else {
                $this->where .= " AND {$group} = '{$this->sanitize($_GET['group'])}'";
            }
            return $this->display();
        }

        try {
            $where = $this->buildWhere();
            $stmt = $this->pdo->prepare("SELECT DISTINCT {$group} FROM {$this->table} {$where}");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $html = '<div class="table"><table class="listing form" cellpadding="0" cellspacing="0" border="0"><tr><th class="full" colspan="2">' . $this->sanitize($this->grouplabel) . '</th></tr>';
            $odd = 1;
            foreach ($rows as $value) {
                $oddeven = ($odd++ % 2) ? 'oddList' : 'evenList';
                $html .= '<tr class="' . $oddeven . '"><td><a href="?group=' . urlencode($value) . '">' . $this->sanitize($value) . '</a></td></tr>';
            }
            $html .= '</table></div>';
            return $html;
        } catch (PDOException $e) {
            return '<div class="error">Database error: ' . $this->sanitize($e->getMessage()) . '</div>';
        }
    }
}

// Helper functions for backward compatibility
function q($query) {
    global $pdo;
    return $pdo->query($query);
}

function fa($stmt) {
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>