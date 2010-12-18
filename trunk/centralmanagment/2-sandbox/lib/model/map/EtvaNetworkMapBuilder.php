<?php


/**
 * This class adds structure of 'network' table to 'propel' DatabaseMap object.
 *
 *
 * This class was autogenerated by Propel 1.3.0-dev on:
 *
 * Fri Aug 28 18:22:07 2009
 *
 *
 * These statically-built map classes are used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 * @package    lib.model.map
 */
class EtvaNetworkMapBuilder implements MapBuilder {

	/**
	 * The (dot-path) name of this class
	 */
	const CLASS_NAME = 'lib.model.map.EtvaNetworkMapBuilder';

	/**
	 * The database map.
	 */
	private $dbMap;

	/**
	 * Tells us if this DatabaseMapBuilder is built so that we
	 * don't have to re-build it every time.
	 *
	 * @return     boolean true if this DatabaseMapBuilder is built, false otherwise.
	 */
	public function isBuilt()
	{
		return ($this->dbMap !== null);
	}

	/**
	 * Gets the databasemap this map builder built.
	 *
	 * @return     the databasemap
	 */
	public function getDatabaseMap()
	{
		return $this->dbMap;
	}

	/**
	 * The doBuild() method builds the DatabaseMap
	 *
	 * @return     void
	 * @throws     PropelException
	 */
	public function doBuild()
	{
		$this->dbMap = Propel::getDatabaseMap(EtvaNetworkPeer::DATABASE_NAME);

		$tMap = $this->dbMap->addTable(EtvaNetworkPeer::TABLE_NAME);
		$tMap->setPhpName('EtvaNetwork');
		$tMap->setClassname('EtvaNetwork');

		$tMap->setUseIdGenerator(true);

		$tMap->addPrimaryKey('ID', 'Id', 'INTEGER', true, null);

		$tMap->addForeignKey('SERVER_ID', 'ServerId', 'INTEGER', 'server', 'ID', true, null);

		$tMap->addColumn('PORT', 'Port', 'VARCHAR', false, 255);

		$tMap->addColumn('IP', 'Ip', 'VARCHAR', false, 255);

		$tMap->addColumn('MASK', 'Mask', 'VARCHAR', false, 255);

		$tMap->addColumn('MAC', 'Mac', 'VARCHAR', false, 255);

		$tMap->addColumn('VLAN', 'Vlan', 'VARCHAR', false, 255);

		$tMap->addColumn('TARGET', 'Target', 'VARCHAR', false, 255);

	} // doBuild()

} // EtvaNetworkMapBuilder
