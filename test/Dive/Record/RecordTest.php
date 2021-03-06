<?php
/*
 * This file is part of the Dive ORM framework.
 * (c) Steffen Zeidler <sigma_z@sigma-scripts.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dive\Test\Record;

use Dive\Log\SqlLogger;
use Dive\Record;
use Dive\RecordManager;
use Dive\Table;
use Dive\TestSuite\Model\User;
use Dive\TestSuite\TestCase;

/**
 * @author Steffen Zeidler <sigma_z@sigma-scripts.de>
 * Date: 30.01.13
 */
class RecordTest extends TestCase
{

    /**
     * @var Table
     */
    private $table;
    /**
     * @var RecordManager
     */
    private $rm;


    protected function setUp()
    {
        parent::setUp();

        $this->rm = self::createDefaultRecordManager();
        $this->table = $this->rm->getTable('user');
    }


    public function testGetOid()
    {
        $recordA = $this->table->createRecord();
        $this->assertNotEmpty($recordA->getOid());
        $recordB = $this->table->createRecord();
        $this->assertNotEquals($recordA->getOid(), $recordB->getOid());
    }


    public function testSetData()
    {
        $data = array(
            'username' => 'John',
            'password' => 'secret',
            'id' => 1234,
            'column1' => 'foo',
            'column2' => 'bar'
        );
        $expected = array(
            'username' => 'John',
            'password' => 'secret',
            'id' => 1234
        );

        $record = $this->table->createRecord();
        $record->setData($data);
        $this->assertEquals($expected, $record->getData());
    }


    public function testGetIdentifierAsString()
    {
        $table = $this->rm->getTable('article2tag');
        $data = array(
            'tag_id' => 11,
            'article_id' => 2
        );
        $record = $table->createRecord($data);
        $this->assertEquals('2' . Record::COMPOSITE_ID_SEPARATOR . '11', $record->getIdentifierAsString());
    }


    public function testGetIdentifierComposite()
    {
        $table = $this->rm->getTable('article2tag');
        $data = array(
            'tag_id' => '11',
            'article_id' => '2'
        );
        $record = $table->createRecord($data);
        $expected = array('article_id' => '2', 'tag_id' => '11');
        $this->assertEquals($expected, $record->getIdentifier());
    }


    public function testGetInternalIdentifierForExistingRecord()
    {
        $record = $this->table->createRecord(array('id' => '1234'), true);
        $this->assertEquals('1234', $record->getInternalId());
    }


    public function testGetInternalIdentifierForNewRecord()
    {
        $record = $this->table->createRecord(array('id' => '1234'), false);
        $this->assertEquals(Record::NEW_RECORD_ID_MARK . $record->getOid(), $record->getInternalId());
    }


    /**
     * @dataProvider provideIdentifier
     * @param string $idValue
     * @param bool   $recordExists
     */
    public function testGetIdentifier($idValue, $recordExists)
    {
        $record = $this->table->createRecord(array('id' => $idValue), $recordExists);
        $this->assertEquals($idValue, $record->getIdentifier());
    }


    /**
     * @dataProvider provideIdentifier
     * @param string $idValue
     * @param bool   $recordExists
     */
    public function testGetIdentifierFieldIndexed($idValue, $recordExists)
    {
        $identifier = array('id' => $idValue);
        $record = $this->table->createRecord($identifier, $recordExists);
        $expected = $idValue === null ? null : $identifier;
        $this->assertEquals($expected, $record->getIdentifierFieldIndexed());
    }


    /**
     * @return array[]
     */
    public function provideIdentifier()
    {
        $idCombinations = array(null, '1234');
        $recordExistsCombinations = array(true, false);
        $testCases = array();
        foreach ($idCombinations as $id) {
            $testCase = array('idValue' => $id);
            foreach ($recordExistsCombinations as $recordExists) {
                $testCase['recordExists'] = $recordExists;
                $testCases[] = $testCase;
            }
        }
        return $testCases;
    }


    /**
     * @return Record
     */
    public function testHasMappedValue()
    {
        $record = $this->table->createRecord();

        // mapped fields are not table fields, same names are possible
        $this->assertFalse($record->hasMappedValue('id'));
        $record->mapValue('id', 'bar');
        $this->assertTrue($record->hasMappedValue('id'));
        return $record;
    }


    /**
     * @depends testHasMappedValue
     * @param \Dive\Record $record
     */
    public function testGetMappedValue(Record $record)
    {
        $this->assertEquals('bar', $record->getMappedValue('id'));
    }


    /**
     * @expectedException \Dive\Record\RecordException
     */
    public function testMapValueThrowsExceptionOnUndefinedField()
    {
        $record = $this->table->createRecord();
        $record->getMappedValue('id');
    }


    /**
     * @dataProvider provideIsModifiedThroughSetData
     *
     * @param array $data
     * @param bool  $exists
     */
    public function testIsModifiedThroughSetData(array $data, $exists)
    {
        $record = $this->table->createRecord(array(), $exists);
        $record->setData($data);
        $this->assertFalse($record->isModified());
    }


    /**
     * @return array[]
     */
    public function provideIsModifiedThroughSetData()
    {
        $testCases = array(
            array(array(), false),
            array(array(), true),
        );
        return array_merge($testCases, $this->provideModifiedTestCases());
    }


    public function testSetDataWithNullValue()
    {
        $fieldName = 'username';
        $fieldValue = 'testuser';

        $record = $this->table->createRecord(array(), false);

        // set a value
        $record->setData(array($fieldName => $fieldValue));
        $actualValue = $record->get($fieldName);
        $this->assertEquals($fieldValue, $actualValue);

        // set value to null
        $record->setData(array($fieldName => null));
        $actualValue = $record->get($fieldName);
        $this->assertNull($actualValue);
    }


    public function testSetFieldWithDefaultValue()
    {
        $article = $this->rm->getTable('article')->createRecord();
        $this->assertEquals('0', $article->get('is_published'));
        $article->set('is_published', true);
        $this->assertEquals('1', $article->get('is_published'));
    }


    /**
     * @dataProvider provideModifiedTestCases
     * @param array $data
     * @param bool  $exists
     * @param array $expected
     */
    public function testIsModifiedThroughSet(array $data, $exists, $expected)
    {
        $initialData = array('username' => 'David');
        $record = $this->table->createRecord($initialData, $exists);
        foreach ($data as $field => $value) {
            $record->set($field, $value);
        }
        $this->assertEquals(!empty($expected), $record->isModified());
    }


    /**
     * @dataProvider provideModifiedTestCases
     * @param array $data
     * @param bool  $exists
     * @param array $expected
     */
    public function testGetModifiedFields(array $data, $exists, $expected)
    {
        $initialData = array('username' => 'David');
        $record = $this->table->createRecord($initialData, $exists);
        foreach ($data as $field => $value) {
            $record->set($field, $value);
        }
        $this->assertEquals($expected, $record->getModifiedFields());
    }


    /**
     * @dataProvider provideModifiedTestCases
     * @param array $data
     * @param bool  $exists
     */
    public function testGetOriginalFieldValue(array $data, $exists)
    {
        $initialData = array('username' => 'David');
        $record = $this->table->createRecord($initialData, $exists);
        foreach ($data as $field => $value) {
            $record->set($field, $value);
        }
        $this->assertEquals('David', $record->getOriginalFieldValue('username'));
    }


    /**
     * @return array[]
     */
    public function provideModifiedTestCases()
    {
        return [
            [['username' => 'John'], false, ['username' => 'David']],
            [['username' => 'John'], true, ['username' => 'David']],
            [['username' => 'David'], false, []],
            [['username' => 'David'], true, []],
        ];
    }


    /**
     * @expectedException \Dive\Table\TableException
     */
    public function testIfFieldModifiedThrowsExceptionOnNonExistingField()
    {
        $record = $this->table->createRecord();
        $record->isFieldModified('not_existing_field');
    }


    public function testRecordReturnsToBeUnmodified()
    {
        $initialData = array('username' => 'David');
        $record = $this->table->createRecord($initialData);
        $record->set('username', 'Micheal');
        $this->assertTrue($record->isModified());
        $record->set('username', 'David');
        $this->assertFalse($record->isModified());
    }


    /**
     * @param  RecordManager $rm
     * @return User
     */
    private function createUserRecord(RecordManager $rm)
    {
        $table = $rm->getTable('user');
        $data = array('username' => 'Joe', 'password' => 'secret password');
        return $table->createRecord($data);
    }


    /**
     * @dataProvider provideDatabaseAwareTestCases
     * @param array $database
     */
    public function testSaveNewRecord(array $database)
    {
        $rm = self::createRecordManager($database);
        $user = $this->createUserRecord($rm);
        $rm->scheduleSave($user);
        $rm->commit();

        $this->assertTrue($user->exists());

        $table = $rm->getTable('user');
        $query = $table->createQuery();
        $query->select('id')
            ->where('id = ?', $user->id)
            ->limit(1);
        $this->assertEquals($user->id, $query->fetchSingleScalar());
    }


    /**
     * @dataProvider provideDatabaseAwareTestCases
     * @param array $database
     */
    public function testSaveExistingRecord(array $database)
    {
        $rm = self::createRecordManager($database);
        $user = $this->createUserRecord($rm);
        $rm->scheduleSave($user);
        $rm->commit();

        $user->username = 'John Doe';
        $user->password = md5('my secret!');
        $rm->scheduleSave($user);
        $rm->commit();

        // removing existing flag from user array
        $userAsArray = $user->toArray();
        unset($userAsArray[Record::FROM_ARRAY_EXISTS_KEY]);

        $table = $rm->getTable('user');
        $query = $table->createQuery();
        $query->where('id = ?', $user->id);

        $this->assertEquals($userAsArray, $query->fetchOneAsArray());
    }


    /**
     * @dataProvider provideDatabaseAwareTestCases
     * @param array $database
     */
    public function testDeleteNewRecord(array $database)
    {
        $rm = self::createRecordManager($database);
        $user = $this->createUserRecord($rm);

        $logger = new SqlLogger();
        $rm->getConnection()->setSqlLogger($logger);
        $rm->scheduleDelete($user);
        $rm->commit();

        $this->assertFalse($user->exists());
        $this->assertEquals(0, $logger->getCount());
    }


    /**
     * @dataProvider provideDatabaseAwareTestCases
     * @param array $database
     */
    public function testDeleteExistingRecord(array $database)
    {
        $rm = self::createRecordManager($database);
        $user = $this->createUserRecord($rm);

        $rm->scheduleSave($user);
        $rm->commit();

        $rm->scheduleDelete($user);
        $rm->commit();

        $table = $rm->getTable('user');
        $query = $table->createQuery();
        $query->select('id')
            ->where('id = ?', $user->id)
            ->limit(1);
        $this->assertFalse($query->fetchSingleScalar());
    }

}
