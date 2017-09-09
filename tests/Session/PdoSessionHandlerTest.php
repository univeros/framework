<?php

namespace Altair\Tests\Session;

use Altair\Session\Adapter\SqlitePdoSessionAdapter;
use Altair\Session\Handler\PdoSessionHandler;
use PDO;
use PHPUnit\Framework\TestCase;

class PdoSessionHandlerTest extends TestCase
{
    private $dbFile;
    /**
     * @var SqlitePdoSessionAdapter
     */
    private $adapter;

    protected function setUp()
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->adapter = new SqlitePdoSessionAdapter($this->getPersistentSqliteDsn(), '', '', 'sessions');
        $this->adapter->getConnection()->exec($this->getCreateSqliteTableStmt());
    }

    protected function tearDown()
    {
        if ($this->dbFile) {
            @unlink($this->dbFile);
        }
    }

    public function testSqliteReadWriteData()
    {
        $handler = $this->getSqliteHandler();

        $handler->open('', 'sid');
        $handler->write('sid', 'test-data');
        $data = $handler->read('sid');
        $this->assertEquals('test-data', $data);
    }

    public function testSessionGc()
    {
        $previousLifeTime = ini_set('session.gc_maxlifetime', 1000);

        $handler = $this->getSqliteHandler();

        $handler->open('', 'sid');
        $handler->read('id');
        $handler->write('id', 'data');
        $handler->close();

        $handler->open('', 'sid');
        $handler->read('gc_id');
        ini_set('session.gc_maxlifetime', -1); // test that you can set lifetime of a session after it has been read
        $handler->write('gc_id', 'data');
        $handler->close();
        $this->assertEquals(2, $this->adapter->getConnection()->query('SELECT COUNT(*) FROM sessions')->fetchColumn());

        $handler->open('', 'sid');
        $data = $handler->read('gc_id');
        $handler->gc(-1);
        $handler->close();

        ini_set('session.gc_maxlifetime', $previousLifeTime);

        $this->assertSame('', $data, 'Session already considered garbage, so not returning data even if it is not pruned yet');
        $this->assertEquals(1, $this->adapter->getConnection()->query('SELECT COUNT(*) FROM sessions')->fetchColumn(), 'Expired session is pruned');

        var_dump($handler->read('gc_id'));
    }

    public function testSessionDestroy()
    {
        $handler = $this->getSqliteHandler();

        $handler->open('', 'sid');
        $data = $handler->read('id');
        $this->assertEmpty($data);

        $handler->write('id', 'data');
        $handler->close();
        $this->assertEquals(1, $this->adapter->getConnection()->query('SELECT COUNT(*) FROM sessions')->fetchColumn());

        $handler->open('', 'sid');
        $data = $handler->read('id');
        $this->assertEquals('data', $data);
        $handler->destroy('id');
        $handler->close();
        $this->assertEquals(0, $this->adapter->getConnection()->query('SELECT COUNT(*) FROM sessions')->fetchColumn());

        $handler->open('', 'sid');
        $data = $handler->read('id');
        $handler->close();

        $this->assertSame('', $data, 'Destroyed session returns empty string');
    }

    protected function getPersistentSqliteDsn()
    {
        $this->dbFile = tempnam(sys_get_temp_dir(), 'altair_sqlite_sessions');

        return 'sqlite:' . $this->dbFile;
    }

    protected function getCreateSqliteTableStmt()
    {
        $sql = "CREATE TABLE sessions (
                id TEXT NOT NULL PRIMARY KEY, 
                content BLOB NOT NULL, 
                session_lifetime INTEGER NOT NULL, 
                session_time INTEGER NOT NULL)";

        return $sql;
    }

    /**
     * @return PdoSessionHandler
     */
    protected function getSqliteHandler()
    {
        $handler = new PdoSessionHandler($this->adapter);

        return $handler;
    }
}
