# Database Migration Guidelines

## Idempotency Requirement

**ALL migrations MUST be idempotent** - they must be safe to run multiple times without errors.

## Why?

Migrations can fail mid-execution due to:
- Network issues
- Database errors
- Deployment failures
- Manual intervention

When this happens, re-running the migration should complete successfully, not fail with "already exists" errors.

## How to Write Idempotent Migrations

### Creating Tables

✅ **GOOD** - Phinx's `create()` is already idempotent:
```php
public function change(): void
{
    $table = $this->table('my_table');
    $table->addColumn('name', 'string')
          ->create();
}
```

### Adding Columns

❌ **BAD** - Will fail if column already exists:
```php
public function change(): void
{
    $table = $this->table('users');
    $table->addColumn('is_admin', 'boolean')
          ->update();
}
```

✅ **GOOD** - Check first:
```php
public function change(): void
{
    $table = $this->table('users');
    if (!$table->hasColumn('is_admin')) {
        $table->addColumn('is_admin', 'boolean')
              ->update();
    }
}
```

### Renaming Columns

❌ **BAD** - Will fail if already renamed:
```php
public function up(): void
{
    $this->execute('ALTER TABLE items RENAME COLUMN old_name TO new_name');
}
```

✅ **GOOD** - Check first:
```php
public function up(): void
{
    $table = $this->table('items');
    if ($table->hasColumn('old_name')) {
        $this->execute('ALTER TABLE items RENAME COLUMN old_name TO new_name');
    }
}
```

### Adding Indexes

❌ **BAD** - Will fail if index exists:
```php
public function change(): void
{
    $this->table('users')
         ->addIndex('email', ['unique' => true])
         ->update();
}
```

✅ **GOOD** - Check first:
```php
public function change(): void
{
    $table = $this->table('users');
    if (!$table->hasIndex('email')) {
        $table->addIndex('email', ['unique' => true])
              ->update();
    }
}
```

### Complex Operations

For complex operations involving multiple steps:

```php
public function up(): void
{
    // Check each step independently
    $table = $this->table('my_table');
    
    // Step 1: Add column if needed
    if (!$table->hasColumn('new_column')) {
        $this->execute('ALTER TABLE my_table ADD COLUMN new_column VARCHAR(255)');
    }
    
    // Step 2: Populate data if needed
    $count = $this->query('SELECT COUNT(*) FROM my_table WHERE new_column IS NOT NULL')->fetchColumn();
    if ($count == 0) {
        $this->execute('UPDATE my_table SET new_column = old_column');
    }
    
    // Step 3: Add constraint if needed
    $hasIndex = $this->query("SHOW KEYS FROM my_table WHERE Key_name = 'idx_new_column'")->rowCount() > 0;
    if (!$hasIndex) {
        $this->execute('ALTER TABLE my_table ADD INDEX idx_new_column (new_column)');
    }
}
```

## Available Check Methods

- `$table->hasTable('table_name')` - Check if table exists
- `$table->hasColumn('column_name')` - Check if column exists
- `$table->hasIndex('column_name')` or `$table->hasIndexByName('index_name')` - Check if index exists
- `$this->query("SHOW KEYS FROM table WHERE Key_name = 'PRIMARY'")->rowCount() > 0` - Check for primary key
- `$this->query("SHOW COLUMNS FROM table LIKE 'column'")->rowCount() > 0` - Alternative column check

## Testing

Before deploying a migration to production:
1. Run it successfully in development
2. Run it **again** in development - it should succeed without errors
3. If it fails on the second run, it's not idempotent!

## Summary

✅ Always check before:
- Adding tables
- Adding columns
- Renaming columns
- Adding indexes
- Adding constraints
- Complex multi-step operations

❌ Never assume a clean slate

