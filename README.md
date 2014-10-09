PHP Database Manager
================

Small and simple class for MySQL database management.

How to use:

Firstly check the `db_manager.php` was included and if your database settings were
defined then follow the below examples.

```php
$db = DM_Manager::get_instance();
```

```php
$db->database();
```

Now for return all results:

Note: The aliase `'u'` is optional.

```php
$db->table = 'users u';
```

```php
$db->select('u.username, u.password')->getAll();
```

You can also to pass array parameters in the methods `select` and `getAll`, for example:

```php
$db->select(array('u.login', 'u.password'))->getAll(2000) // will be returned 2000.;
```

Use the `get()` method instead `getAll()` if you want return only one result,
for example:

```php
$db->select('u.username, u.password')->where('username',
'fulano')->where('password', '***')->get();
```

If you want insert record in the database, see this example:

```php
$db->values(array('username' => 'fulano', 'password' => '***'))->insert();
```

If you want update some record in the database, see this example:

```php
$db->set(array('username' => 'sicrano'))->where('username',
'fulano')->update();
```

for delete some record in the database, you can use:

```php
$db->where('username', 'fulano')->delete();
```

Many others functions can be explored in the class, see documentation for more details, below i go show some fast examples.

Queries with `where` method:
```php
$db->select('*')->where('username', 'fulano')->getAll();
```

Queries with `where_between` method:
```php
$db->select('*')->where_between('year', 2013, 2014)->getAll();
```

Queries with `where_in` method:
```php
$db->select('*')->where_in('numbers', array(10,11,15,19,25))->getAll();
```

Queries with `order_by` method:
```php
$db->select('*')->where('username', 'fulano')->order_by('age', 'DESC')->getAll();
```

Queries with `group_by` method:
```php
$db->select('count(*) as count, age')->group_by('age')->getAll();
```

Queries with `group_by` method:
```php
$db->select('count(*) as count, age')->group_by('age')->getAll();
```

Queries with `join` method:
```php
$db->table = 'users u';
$db->select('u.name, g.name')->join('genders g', 'u.gender_id = g.id', 'LEFT')->getAll();
```

Method `get_result_count` return the number total records:
```php
$db->table = 'users u';
$db->->get_result_count();
```

You can increase the limit of records will be returned and change the primary key field name.
```php
$db->limit = 2000;
$db->primaryKey = 'name_your_primary_key_field'; // default is id.
```