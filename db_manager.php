<?php
/**
 * Pequeno e simples gestor mysql com base em PDO.
 *
 * Esta classe tem como objetivo gerir consultas e também outras operações
 * à base de dados MySQL, pode-se usar outros drivers, basta setar os dados
 * no ficheiro de configuração.
 *
 * @package engine
 * @subpackage classes
 * @author  Vagner V. B. Cantuares <vagner.cantuares@gmail.com>
 * @copyright copyright (c) copyright 2014
 * @license http://opensource.org/licenses/gpl-license.php GNU Public Licence (GPL)
 * @link https://www.facebook.com/vagner.cantuares
 */

class DB_Manager extends PDO
{

    /**
     * Chave primária.
     *
     * @var string
     */
    public $primaryKey = 'id';

    /**
     * Tabela da base de dados.
     *
     * @var void
     */
    public $table;

    /**
     * Query.
     *
     * @var void
     */
    private $_query;

    /**
     * SELECT.
     *
     * @var void
     */
    private $_select;

    /**
     * Alias - usado para renomear uma tabela.
     *
     * @var void
     */
    private $_alias;

    /**
     * Relação entre tabelas.
     *
     * @var void
     */
    private $_join;

    /**
     * UPDATE.
     *
     * @var void
     */
    private $_update;

    /**
     * Colunas que receberam valores para
     * posteriomente serem atualizados/inseridas.
     *
     * @var void
     */
    private $_data_set;

    /**
     * DELETE.
     *
     * @var void
     */
    private $_delete;

    /**
     * DELETE ALL.
     *
     * @var void
     */
    private $_delete_all;

    /**
     * INSERT.
     *
     * @var array
     */
    private $_insert = array();

    /**
     * Códigos de vinculação dos
     * dados inseridos.
     *
     * @var array
     */
    private $_insert_binds = array();

    /**
     * Condições.
     *
     * @var void
     */
    private $_where;

    /**
     * Dados armazenados.
     *
     * @var array
     */
    private $_data = array();

    /**
     * Grupo de resultados.
     *
     * @var void
     */
    private $_group_by;

    /**
     * Ordem dos resultados.
     *
     * @var void
     */
    private $_order_by;

    /**
     * Ponto de partida dos dados.
     *
     * @var integer
     */
    private $_offset = 0;

    /**
     * Limite dos dados.
     *
     * @var integer
     */
    private $_limit = 1000;

    /**
     * Resultado da query.
     *
     * @var array
     */
    private $_result = array();

    /**
     * Total de resultados retornados.
     *
     * @var integer
     */
    private $_count = 0;

    /**
     * Define se há mais que um resultado.
     * @var boolean
     */
    private $all = false;

    /**
     * Define se há apenas um resultado.
     * @var boolean
     */
    private $row = false;

    /**
     * Instância.
     *
     * @var void
     */
    public static $instance;

    /**
     * Método construtor da classe.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Singleton da classe.
     *
     * @return object Objeto da clase atual.
     */
    public static function get_instance()
    {
        $class = __CLASS__;
        if (! self::$instance) {
            self::$instance = new $class();
        }
        return self::$instance;
    }

    /**
     * Instância da base de dados
     *
     * @return object Instância da classe.
     */
    public function database()
    {
        extract(include('./config/database.php'));
        $pdo_errconf = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING);
        parent::__construct($driver.':dbname='.$database.';host='.$host, $user, $password, $pdo_errconf);
        return $this;
    }

    /**
     * Contrução de colunas para consultas.
     *
     * @param  string|array $columns Nome das colunas, pode ser string ou array.
     * @return object                Instância da classe.
     */
    public function select($columns = '*')
    {
        if (is_null($this->table)) {
            return false;
        }

        $this->_alias = explode(' ', $this->table);

        $this->_count = 0;

        if (count($this->_alias) > 1) {
            $this->_alias = $this->_alias[1];
        } else {
            $this->_alias = $this->_alias[0];
        }

        if (is_array($columns)) {
            $columns = implode(',', $columns);
        }

        $this->_select = "{$columns}";

        return $this;
    }

    /**
     * Atualiza os daddos na base de dados.
     *
     * @param  string|array $columns Valores a serem atualizados.
     * @return object 				 Instância da classe.
     */
    public function set($columns = null)
    {
        if (is_null($this->table)) {
            return false;
        }

        if (! is_array($columns)) {
            return false;
        }

        if (is_null($this->_data_set)) {
            if ($this->_where) {
                return false;
            }
        }

        $update_values = array();

        foreach ($columns as $key => $value) {
            array_push($update_values, "{$key} = ?");
            array_push($this->_data, $value);
        }

        $this->_update = true;

        $update_values  = implode(',', $update_values);
        $this->_data_set = "{$update_values}";

        return $this;
    }

    /**
     * Valores que serão definidos para inserção
     * na base de dados.
     *
     * @param  array $columns  Colunas com seus respetivos valores.
     * @return object          Instância da classe.
     */
    public function values($columns)
    {
        if (! is_array($columns)) {
            return false;
        }

        foreach ($columns as $key => $value) {
            array_push($this->_insert, "`{$key}`");
            array_push($this->_data, $value);
            array_push($this->_insert_binds, '?');
        }

        $this->_insert       = implode(',', $this->_insert);
        $this->_insert_binds = implode(',', $this->_insert_binds);

        return $this;
    }

    /**
     * Cria um novo registro na base de dados.
     *
     * @return object Ordem de execução.
     */
    public function insert()
    {
        if (is_null($this->table)) {
            return false;
        }

        if (is_null($this->_insert) && is_null($this->_insert_binds)) {
            return false;
        }

        return $this->_execute($this->query_builder(), $this->_data);
    }

    /**
     * Atualiza um registro na base de dados.
     *
     * @return object Ordem de execução.
     */
    public function update()
    {
        if (is_null($this->table)) {
            return false;
        }

        if (is_null($this->_where)) {
            return false;
        }

        return $this->_execute($this->query_builder(), $this->_data);
    }

    /**
     * Remove um registro na base de dados.
     *
     * @param  string|array $set_select Valores a serem atualizados.
     * @return object Ordem de execução.
     */
    public function delete()
    {
        if (is_null($this->table)) {
            return false;
        }

        if (is_null($this->_where)) {
            return false;
        }

        $this->_delete = true;

        return $this->_execute($this->query_builder(), $this->_data);
    }

    /**
     * Remove todos os registros na base de dados.
     *
     * @return object Ordem de execução.
     */
    public function delete_all()
    {
        if (is_null($this->table)) {
            return false;
        }
        $this->_delete_all = true;

        return $this->_execute($this->query_builder(), $this->_data);
    }

    /**
     * Cria uma relação entre uma ou mais tabelas.
     *
     * @param  string $table   Tabela que irá ser relacionada.
     * @param  string $columns Coluna a ser comparada.
     * @param  string $side    Lado da relação.
     * @return object          Instância da classe.
     */
    public function join($table = '', $columns = null, $side = 'INNER')
    {
        if (is_null($this->table)) {
            return false;
        }

        $sql_code = "          " .
        "	{$side}            " .
        "JOIN                  " .
        "	{$table}           " .
        "ON {$columns}         " ;

        if ($this->_join) {
            $this->_join .= $sql_code;
        } else {
            $this->_join = $sql_code;
        }

        return $this;
    }

    /**
     * Condições da Query SQL.
     *
     * @param  string     $column Colunas
     * @param  string|int $value  Valor da condicional
     * @param  string     $logical_operator operador lógico.
     * @return object     		  Instância da classe
     */
    public function where($column = null, $value = null, $logical_operator = 'AND')
    {
        if (is_null($column) && is_null($value)) {
            return false;
        }

        $column = explode(' ', $column);
        $count  = count($column);

        if ($count == 1) {
            $content = '= ?';
        }

        if ($count == 2) {
            $content = "{$column[1]} ?";
        }

        if ($count >= 3) {
            $content = trim(strstr(implode(' ', $column), ' '));
        }

        $column = $column[0];

        array_push($this->_data, $value);

        if (!is_null($this->_where)) {
            $this->_where .= " {$logical_operator} {$column} {$content}";
        }

        if (is_null($this->_where)) {
            $this->_where = "WHERE {$column} {$content}";
        }

        return $this;
    }

    /**
     * Condição where para lista.
     *
     * @param  string     $column  Coluna
     * @param  string|int $values  Valor da condicional
     * @param  string     $logical_operator operador lógico.
     * @return object     		   Instância da classe
     */
    public function where_in($column = null, $values = null, $logical_operator = 'AND')
    {
        if (is_null($column) && is_null($values)) {
            return false;
        }

        array_push($this->_data, $values);

        if (!is_null($this->_where)) {
            $this->_where .= " {$logical_operator} {$column} IN (?)";
        }

        if (is_null($this->_where)) {
            $this->_where = "WHERE {$column} IN (?)";
        }

        return $this;
    }

    /**
     * Separa por grupos.
     *
     * @param  string $columns Colunas
     * @return object          Instância da classe
     */
    public function group_by($columns = '')
    {
        if (is_array($columns)) {
            $columns = implode(',', $columns);
        }

        $this->_group_by = "GROUP BY {$columns}";
        return $this;
    }

    /**
     * Condição where entre intervalos.
     *
     * @param  string $column Coluna.
     * @param  string $left   Valor esquerdo.
     * @param  string $right  Valor direito.
     * @return object         Instância da classe
     */
    public function where_between($column = null, $left = null, $right = null)
    {
        if (is_null($column) && is_null($left) && is_null($right)) {
            return false;
        }

        array_push($this->_data, $left);
        array_push($this->_data, $right);

        if (!is_null($this->_where)) {
            $this->_where .= " AND ( {$column} BETWEEN ? AND ? )";
        }

        if (is_null($this->_where)) {
            $this->_where = "WHERE ( {$column} BETWEEN ? AND ? )";
        }

        return $this;
    }

    /**
     * Retorna um tipo de ordem, DES ou ASC.
     *
     * @param  string $columns Colunas
     * @param  string $sort    Tipo de ordem
     * @return object          Instância da classe
     */
    public function order_by($columns = '', $sort = 'ASC')
    {
        if (is_array($columns)) {
            $columns = implode(',', $columns);
        }

        $this->_order_by = "ORDER BY {$columns} {$sort}";
        return $this;
    }

    /**
     * Retorna todos os valores de uma consulta.
     * Agora os resultados são retornados como objetos.
     *
     * @param  int   $limit  Limite de dados retornados
     * @param  int   $offset Deslocamento das consultas.
     * @return array         Retorno dos dados solicitados.
     */
    public function all($limit = null, $offset = null)
    {
        if (!is_null($limit)) {
            $this->_limit  = $limit;
        }

        if (!is_null($offset)) {
            $this->_offset = $offset;
        }

        $this->all = true;
        return $this->_execute($this->query_builder(), $this->_data);
    }

    /**
     * Retorna todos os valores de uma consulta
     *
     * @param  int   $limit  Limite de dados retornados
     * @param  int   $offset Deslocamento das consultas.
     * @return array         Retorno dos dados solicitados.
     */
    public function all_array($limit = null, $offset = null)
    {
        if (!is_null($limit)) {
            $this->_limit  = $limit;
        }

        if (!is_null($offset)) {
            $this->_offset = $offset;
        }

        $this->all = true;
        return $this->_execute($this->query_builder(), $this->_data, 'array');
    }

    /**
     * Retorna apenas uma consulta.
     *
     * @return array   Retorno dos dados solicitados.
     */
    public function row()
    {
        $this->row = true;
        return $this->_execute($this->query_builder(), $this->_data);
    }

    /**
     * Faz a execução de uma query passada
     * como parâmetro de entrada.
     *
     * @param  string $query  Query SQL.
     * @param  array  $values Valores da query.
     * @param  string $type  Tipo de retorno de dados.
     * @return mixed          Resultado da consulta.
     */
    private function _execute($query = null, $values = array(), $type = 'obj')
    {
        if (is_null($query)) {
            return false;
        }

        $result = array();
        $sth    = $this->prepare($query);

        if ($sth->execute(array_values(array_filter($values, 'strlen')))) {
            if ($this->_insert || $this->_update || $this->_delete || $this->_delete_all) {
                $this->clear();
                return true;
            }

            switch ($type) {
                case 'array':
                    if ($this->all) {
                        $result = $sth->fetchAll(PDO::FETCH_ASSOC);
                    }
                    if ($this->row) {
                        $result = $sth->fetch(PDO::FETCH_ASSOC);
                    }
                    break;

                default:
                    if ($this->all) {
                        $result = $sth->fetchAll(PDO::FETCH_OBJ);
                    }
                    if ($this->row) {
                        $result = $sth->fetch(PDO::FETCH_OBJ);
                    };
                    break;
            }
        }

        #if ( $result )
        #	array_map(array($this, 'map_count'), $result);

        $this->clear();

        return $result;
    }

    /**
     * Método CALL - Especialmente para stored precedure.
     *
     * @param  string $name Nome da rotina.
     * @return void
     */
    public function call($name = '')
    {
    }

    /**
     * Callback que conta os resultados
     * retornados da consulta.
     *
     * @param  array $rows Resultado da consulta.
     * @return int         Número de resultados.
     */
    private function map_count($rows)
    {
        $this->_count += 1;
    }

    /**
     * Retorna a quantidade resultados de uma consulta.
     *
     * @return int Total dee resultados retornados.
     */
    public function get_result_count()
    {
        return $this->_count;
    }

    /**
     * Depuração
     *
     * @param  mixed $vars Conteúdo a ser depurado.
     * @return void
     */
    private function debug_query($vars = null)
    {
        if (is_null($vars)) {
            return false;
        }

        echo '<!-- SQL - DEBUG -->';
        echo '<pre>';
        var_export($vars);
        echo '</pre>';
        echo '<!-- SQL - DEBUG -->';
    }

    /**
     * Depuração SQL.
     *
     * @return void
     */
    public function check_query()
    {
        return $this->debug_query($this->query_builder());
    }

    /**
     * Contrução de uma estrutura SQL.
     *
     * @return string
     */
    private function query_builder()
    {
        if (is_null($this->table)) {
            return $this;
        }

        $sql = null;

        if ($this->_select) {
            $sql = "SELECT                               " .
                   "    {$this->_select}                 " .
                   "FROM                                 " .
                   "    {$this->table}                   " .
                   "    {$this->_join}                   " .
                   "    {$this->_where}                  " .
                   "    {$this->_group_by}               " .
                   "    {$this->_order_by}               " .
                   "LIMIT                                " .
                   "    {$this->_offset},{$this->_limit} " ;
        }

        if ($this->_insert) {
            $sql = "INSERT INTO                          " .
                   "	{$this->table}                   " .
                   "    ({$this->_insert})               " .
                   "VALUES                               " .
                   "    ({$this->_insert_binds})         " ;
        }

        if ($this->_update) {
            $sql = "UPDATE                               " .
                   "    {$this->table}                   " .
                   "SET                                  " .
                   "    {$this->_data_set}               " .
                   "    {$this->_where}                  " ;
        }

        if ($this->_delete) {
            $sql = "DELETE FROM                          " .
                   "    {$this->table}                   " .
                   "    {$this->_where}                  " ;
        }

        if ($this->_delete_all) {
            $sql = "DELETE FROM {$this->table}          " ;
        }

        if ($sql) {
            return preg_replace("!\s+!", " ", trim($sql));
        }
    }

    /**
     * Limpa as variáveis para que futuras novas queries
     * sejam usadas.
     *
     * @return void
     */
    private function clear()
    {
        $clear_null = array(
            '_query',    '_select', '_alias', '_join', '_update', '_delete_all',
            '_data_set', '_delete', '_where', '_group_by', '_order_by', 'all', 'row');

        $clear_array = array(
            '_data', '_insert', '_result', '_insert_binds');

        foreach ($clear_null as $v) {
            $this->{$v} = null;
        }

        foreach ($clear_array as $v) {
            $this->{$v} = array();
        }
    }
}
