<?php

namespace Database;

class Manager
{
    public $pdo;
    public $primary_key = 'id';
    public $select;
    public $table;
    public $join = [];
    public $where = [];
    public $order_by = [];
    public $group_by = [];
    public $having;
    private $query;
    private $data = [];
    private $limit;
    private $page = 0;
    private $sth;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function pagination($table = '', $limit = 1000, $page = 1, $cols = '', $conditions = [])
    {
        if (!$table) {
            return false;
        }
        if ($cols) {
            $this->select(['COUNT(*) as total',$cols]);
        } else {
            $this->select('COUNT(*) as total');
        }
        if ($conditions) {
            $this->where($conditions);
        }
        $this->from($table);
        $result = $this->execute()->fetch(PDO::FETCH_OBJ);
        if (!$result) {
            return array(
                'num_rows' => 0,
                'total'    => 0,
                'prev'     => 1,
                'next'     => 1);
        }
        $num_rows = $result->total;
        $total_pages = ceil(($num_rows/$limit));
        return array(
            'num_rows' => $num_rows,
            'total'    => $total_pages,
            'prev'     => ($page <= 1 ? 1 : $page - 1),
            'next'     => ($page == $total_pages ? $total_pages : $page + 1)
        );
    }

    public function select($columns)
    {
        if (is_array($columns)) {
            $columns = implode(', ', $columns);
        } else {
            $columns = '*';
        }
        $this->select = $columns;
        return $this;
    }

    public function from($table = '*')
    {
        $this->table = $table;
        return $this;
    }

    public function join($table = '', $str = '', $side = 'INNER')
    {
        array_push($this->join, sprintf('%s JOIN %s ON %s', $side, $table, $str));
        return $this;
    }

    public function where_pure($column, $value = '', $operator = 'AND')
    {
        if (is_array($column)) {
            $value  = array_values($column);
            $column = array_keys($column);
            $column = $column[0];
            $value  = $value[0];
        }
        $column = explode(' ', $column);
        if (count($column) > 1) {
            $column = sprintf('%s %s', $column[0], $column[1]);
        } else {
            $column = sprintf('%s %s', $column[0], '=');
        }
        if (!$this->where) {
            array_push($this->where, sprintf('WHERE %s %s', $column, $value));
        } else {
            array_push($this->where, sprintf('%s %s %s', $operator, $column, $value));
        }
        return $this;
    }

    public function where($column, $value = '', $operator = 'AND')
    {
        if (is_array($column)) {
            $value  = array_values($column);
            $column = array_keys($column);
            $column = $column[0];
            $value  = $value[0];
        }
        $column = explode(' ', $column);
        if (count($column) > 1) {
            $column = sprintf('%s %s', $column[0], $column[1]);
        } else {
            $column = sprintf('%s %s', $column[0], '=');
        }
        if (!$this->where) {
            array_push($this->where, sprintf('WHERE %s ?', $column));
        } else {
            array_push($this->where, sprintf('%s %s ?', $operator, $column));
        }
        array_push($this->data, $value);
        return $this;
    }

    public function start_group_where($column = '', $value = '', $operator = 'AND')
    {
        $column = explode(' ', $column);
        if (count($column) > 1) {
            $column = sprintf('%s %s', $column[0], $column[1]);
        } else {
            $column = sprintf('%s %s', $column[0], '=');
        }
        if (!$this->where) {
            array_push($this->where, sprintf('WHERE (%s ?', $column));
        } else {
            array_push($this->where, sprintf('%s (%s ?', $operator, $column));
        }
        array_push($this->data, $value);
        return $this;
    }

    public function close_group_where($column = '', $value = '', $operator = 'AND')
    {
        $column = explode(' ', $column);
        if (count($column) > 1) {
            $column = sprintf('%s %s', $column[0], $column[1]);
        } else {
            $column = sprintf('%s %s', $column[0], '=');
        }
        if (!$this->where) {
            return false;
        }
        array_push($this->where, sprintf('%s %s ?)', $operator, $column));
        array_push($this->data, $value);
        return $this;
    }

    public function where_in($column = '', $value = '', $operator = 'AND')
    {
        if (!$this->where) {
            if (is_array($value)) {
                array_push($this->where, sprintf('WHERE %s IN (%s)', $column, str_repeat('?,', count($value) - 1) . '?'));
            } else {
                array_push($this->where, sprintf('WHERE %s IN (?)', $column));
            }
        } elseif (is_array($value)) {
            array_push($this->where, sprintf('%s %s IN (%s)', $operator, $column, str_repeat('?,', count($value) - 1) . '?'));
        } else {
            array_push($this->where, sprintf('%s %s IN (?)', $operator, $column));
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                array_push($this->data, $v);
            }
        } else {
            array_push($this->data, $value);
        }
        return $this;
    }

    public function where_not_in($column, $value, $operator = 'AND')
    {
        if (!$this->where) {
            array_push($this->where, sprintf('WHERE %s NOT IN (?)', $column));
        } else {
            array_push($this->where, sprintf('%s %s NOT IN (?)', $operator, $column));
        }
        if (is_array($value)) {
            array_push($this->data, implode(',', $value));
        } else {
            array_push($this->data, $value);
        }
        return $this;
    }

    public function where_between($column, $a = '', $b = '', $operator = 'AND')
    {
        if (!$this->where) {
            array_push($this->where, sprintf('WHERE %s BETWEEN ? AND ?', $column));
        } else {
            array_push($this->where, sprintf('%s %s BETWEEN ? AND ?', $operator, $column));
        }
        array_push($this->data, $a);
        array_push($this->data, $b);
        return $this;
    }

    public function where_like($column, $value, $operator = 'AND')
    {
        if (!$this->where) {
            array_push($this->where, sprintf('WHERE %s LIKE ?', $column));
        } else {
            array_push($this->where, sprintf('%s %s LIKE ?', $operator, $column));
        }
        if (is_array($value)) {
            array_push($this->data, implode(',', $value));
        } else {
            array_push($this->data, $value);
        }
        return $this;
    }

    public function order($column, $sort = 'ASC')
    {
        if (is_array($column)) {
            $sort   = array_values($column);
            $column = array_keys($column);
            $sort   = $sort[0];
            $column = $column[0];
        }
        if (!$this->order_by) {
            array_push(
                $this->order_by,
                sprintf('ORDER BY %s %s', $column, $sort)
            );
        } else {
            array_push(
                $this->order_by,
                sprintf('%s %s', $column, $sort)
            );
        }

        return $this;
    }

    public function group($column)
    {
        if (is_array($column)) {
            $column   = array_values($column);
            $column = $column[0];
        }
        if (!$this->group_by) {
            array_push(
                $this->group_by,
                sprintf('GROUP BY %s', $column)
            );
        } else {
            array_push(
                $this->group_by,
                sprintf('%s', $column)
            );
        }

        return $this;
    }

    public function having($column = '', $value = '')
    {
        $column = explode(' ', $column);
        if (count($column) > 1) {
            $column = sprintf('%s %s', $column[0], $column[1]);
        } else {
            $column = sprintf('%s %s', $column[0], '=');
        }
        if ($this->having) {
            return false;
        }
        $this->having = sprintf('HAVING %s ?', $column);
        array_push($this->data, $value);

        return $this;
    }

    public function limit($limit = 1000, $offset = 0, $isOffset = false)
    {
        $this->limit = sprintf('LIMIT %s,%s', ($isOffset ? $offset : $this->page*$limit), $limit);

        return $this;
    }

    public function page($page = 0)
    {
        if (($page - 1) <= 0) {
            $page = 1;
        }
        $this->page = $page - 1;

        return $this;
    }

    public function execute()
    {
        if (!$this->table || !$this->select) {
            return false;
        }
        $this->query = 'SELECT %s FROM %s %s %s %s %s %s %s';
        $this->query = sprintf(
            $this->query,
            $this->select,
            $this->table,
            implode(' ', $this->join),
            implode(' ', $this->where),
            $this->having,
            implode(', ', $this->group_by),
            implode(', ', $this->order_by),
            $this->limit
        );
        $this->sth = $this->pdo->prepare(trim($this->query));
        $result = $this->sth->execute($this->data);

        if (!$result) {
            return false;
        }
        $this->clear();

        return $this->sth;
    }

    public function query($sql = '', $data = [])
    {
        if (!$this->table || !$this->select) {
            return false;
        }
        $this->data = $data;
        $this->query = $sql;
        $this->sth = $this->pdo->prepare(trim($this->query));
        $result = $this->sth->execute($this->data);
        if (!$result) {
            return false;
        }
        $this->clear();

        return $this->sth;
    }

    public function rawSql($sql = '', $data = [])
    {
        $this->data = $data;
        $this->query = $sql;
        $this->sth = $this->pdo->prepare(trim($this->query));
        $result = $this->sth->execute($this->data);
        if (!$result) {
            return false;
        }
        $this->clear();

        return $this->sth;
    }

    public function insert($table = '', $data = [])
    {
        $this->table = $table;
        $this->data = $columns = $values = [];
        foreach ($data as $k => $v) {
            array_push($this->data, $v);
            array_push($columns, sprintf('`%s`', $k));
            array_push($values, '?');
        }
        $this->query = 'INSERT INTO %s (%s) VALUES (%s)';
        $this->query = sprintf($this->query, $this->table, implode(',', $columns), implode(',', $values));
        $this->sth = $this->pdo->prepare(trim($this->query));
        $result = $this->sth->execute($this->data);
        if (!$result) {
            return false;
        }
        $this->clear();

        return $result;
    }

    public function insertBatch($table = '', $data = [])
    {
        if (!$data) {
            return false;
        }
        $count_a = 0;
        $this->table = $table;
        $this->data = $columns = $str = $values = [];
        foreach ($data as $v) {
            foreach ($v as $y => $z) {
                array_push($this->data, $z);
                if (!$count_a) {
                    array_push($columns, sprintf('`%s`', $y));
                }
                array_push($values, '?');
            }
            array_push($str, sprintf('(%s)', implode(',', $values)));
            $values = [];
            $count_a++;
        }
        $this->query = 'INSERT INTO %s (%s) VALUES %s';
        $this->query = sprintf($this->query, $this->table, implode(',', $columns), implode(',', $str));
        $this->sth = $this->pdo->prepare(trim($this->query));
        $result = $this->sth->execute($this->data);
        if (!$result) {
            return false;
        }
        $this->clear();

        return $result;
    }

    public function update($table = '', $data = [], $c1 = [], $c2 = [])
    {
        if (!$data) {
            return false;
        }
        $str = $values = [];
        $this->table = $table;
        if ($c1) {
            $this->where($c1);
        }
        if ($c2) {
            $this->where($c2);
        }
        foreach ($data as $k => $v) {
            array_push($values, $v);
            array_push($str, sprintf('%s = ?', $k));
        }
        $this->query = 'UPDATE %s %s SET %s %s';
        $this->query = sprintf(
            $this->query,
            $this->table,
            implode(' ', $this->join),
            implode(', ', $str),
            implode(' ', $this->where)
        );
        $this->sth = $this->pdo->prepare(trim($this->query));
        $result = $this->sth->execute(array_merge($values, $this->data));
        if (!$result) {
            return false;
        }
        $this->clear();

        return $result;
    }

    public function delete($table = '', $c1 = [], $c2 = [])
    {
        $this->table = $table;
        if ($c1) {
            $this->where($c1);
        }
        if ($c2) {
            $this->where($c2);
        }
        $this->query = 'DELETE FROM %s %s';
        $this->query = sprintf($this->query, $this->table, implode(' ', $this->where));
        $this->sth = $this->pdo->prepare(trim($this->query));
        $result = $this->sth->execute($this->data);
        if (!$result) {
            return false;
        }
        $this->clear();

        return $result;
    }

    public function deleteAll($table = '')
    {
        $this->table = $table;
        $this->query = 'DELETE FROM %s';
        $this->query = sprintf($this->query, $this->table);
        $this->sth = $this->pdo->prepare(trim($this->query));
        $result = $this->sth->execute();
        if (!$result) {
            return false;
        }
        $this->clear();

        return $result;
    }

    public function insertOnDuplicate($table, $data, $batch = false)
    {
        $columns = $arr = $update = $binds = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $y => $x) {
                    array_push($columns, '`'.$y.'`');
                    array_push($update, $y.'=VALUES(`'.$y.'`)');
                }
            } else {
                array_push($columns, '`'.$k.'`');
                array_push($update, $k.'=VALUES(`'.$k.'`)');
            }
        }
        if ($batch) {
            foreach ($data as $k => $v) {
                foreach ($v as $x) {
                    array_push($arr, $x);
                }
                array_push($binds, '('.str_repeat('?,', count($v) - 1) . '?'.')');
            }
            $data=$arr;
            $binds = implode(',', $binds);
        } else {
            $data  = array_values($data);
            $binds = '('.str_repeat('?,', count($data) - 1) . '?'.')';
        }
        $columns = implode(',', array_unique($columns));
        $update  = implode(',', array_unique($update));
        $sql=
        'INSERT INTO %s (%s) '.
        'VALUES %s           '.
        'ON DUPLICATE KEY    '.
        'UPDATE %s;          ';
        $sql=sprintf($sql, $table, $columns, $binds, $update);
        $sth = $this->pdo->prepare(trim($sql));
        $result = $sth->execute($data);
        if (!$result) {
            return false;
        }
        $this->clear();

        return $result;
    }

    public function getCalcRows()
    {
        return $this->rawSql('SELECT FOUND_ROWS() AS num_rows')->fetch(\PDO::FETCH_OBJ)->num_rows;
    }

    public function startTransaction()
    {
        $this->pdo->query('START TRANSACTION;');
        $this->pdo->query('SET autocommit=0;');
    }

    public function commit()
    {
        $this->pdo->query('COMMIT;');
    }

    public function rollback()
    {
        $this->pdo->query('ROLLBACK;');
    }

    private function clear()
    {
        $this->select   = null;
        $this->table    = null;
        $this->join     = [];
        $this->where    = [];
        $this->order_by = [];
        $this->group_by = [];
        $this->having   = null;
        ;
        $this->query    = null;
        $this->data     = [];
        $this->limit    = null;
        $this->page     = 0;
    }

    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }
}
