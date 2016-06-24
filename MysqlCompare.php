<?php

//  
//  Super simple mysql comparison done via CLI.
//  
//  How?  Install MDB2 & relevant driver (mysql or mysqli) and type at the command line
//  
//  MysqlCompare.php mysql://root:xxxx@myserv1.whatevs.com/DBNAME mysql://root:xxxx@myserv2.whatevs.com/DBNAME
//  

require 'MDB2.php';

class MysqlCompare
{
	public $dsn2 = "mysql://root:xxx@server1/dbName";
	public $dsn1 = "mysql://root:xxx@server2/dbName";
	
	public function __construct()
	{
		$this->db = array();
		if($_SERVER['argv'][1] && $_SERVER['argv'][2]){
			$this->dsn1 = $_SERVER['argv'][1];
			$this->dsn2 = $_SERVER['argv'][2];
		}
		$this->db[0]['handle'] = $this->makeHandle($this->dsn1);//$this->makeHandle($_SERVER['argv'][1]);

		$this->db[1]['handle'] = $this->makeHandle($this->dsn2);//$this->makeHandle($_SERVER['argv'][2]);

		$this->getInfo();
		
		$this->getTableDiffs();
		$this->getColumnDiffs();
		
		$this->reportDiffs();
		
		if(isset($synch)){
			//Do the synch here if requested.
			// todo
		}
		
	}
	
	private function makeHandle($dsn)
	{
		//echo $dsn;
		$db =& MDB2::connect($dsn);
//		print_r($db);
		$db->loadModule('Extended');
		if (PEAR::isError($db)) {
    		die($db->getMessage());
		}
		else{
			return $db;
		}
		
	}
	
	private function getInfo()
	{
		//loop through the two dbs
		for($i =0; $i <= 1; $i++){
			//Get the tables
			$q = "SHOW TABLES";
			$this->db[$i]['tables'] = array();
			$tables =& $this->db[$i]['handle']->extended->getCol($q,null,null,null,0);
			//Loop through the tables to get the table description
			foreach($tables as $table){
			//echo "---Tables---\n\n".$table ."\n\n";
				$q = "DESCRIBE ".$table.";";
				$tableInfo = $this->db[$i]['handle']->extended->getAll($q, null, null, null,MDB2_FETCHMODE_ASSOC);
				foreach($tableInfo as $columnInfo){
					$this->db[$i]['tables'][$table][$columnInfo['field']] = $columnInfo;
				}
			}
			//print_r($this->db[$i][tables]);
		}
		
	}
	
	private function getTableDiffs()
	{
		//Find diffs in Tables
		$this->tableDiffs = array();
		$this->tableDiffs['db0'] = array();
		$this->tableDiffs['db1'] = array();
		$missingTables = array_diff(array_keys($this->db[0]['tables']),array_keys($this->db[1]['tables']));
		foreach($this->tableDiffs as $missing){
			//echo" Table ".$missing." does not exist on db2\n";
		}
		foreach($missingTables as $mt){
			array_push($this->tableDiffs['db1'],$mt);
		}
		
		$missingTables = array_diff(array_keys($this->db[1]['tables']),array_keys($this->db[0]['tables']));
		foreach($this->tableDiffs as $missing){
			//echo" Table ".$missing." does not exist on db2\n";
		}
		foreach($missingTables as $mt){
			array_push($this->tableDiffs['db0'],$mt);
		}
	}
	
	private function getColumnDiffs()
	{
		$this->columnDiffs = array();
		//print_r($this->tableDiffs);
		// Use all the tables that are in the 2nd db for the loop
		foreach($this->db[0]['tables'] as $table=>$tableInfo){
			
			//echo $table."\n";
			
			if(array_key_exists($table,$this->db[1]['tables'])){
				
				$this->columnDiffs['db1'][$table]['missing'] = array();
				$this->columnDiffs['db1'][$table]['attributeDiffs'] = array();
				foreach($tableInfo as $colName=>$colInfo){
					//echo "\t".$colName."\n";
					// All db1 table cols exist in db2
					if(!(array_key_exists($colName,$this->db[1]['tables'][$table]))){
						//echo "\t".$colName." missing in db2 \n";
						array_push($this->columnDiffs['db1'][$table]['missing'],$colName);
					}
					elseif(array_diff_assoc($colInfo,$this->db[1]['tables'][$table][$colName])){
						$this->columnDiffs['db1'][$table]['attributeDiffs'][$colName] = array_diff_assoc($colInfo,$this->db[1]['tables'][$table][$colName]);
					}
				}
				
				$this->columnDiffs['db0'][$table]['missing'] = array();
				$this->columnDiffs['db0'][$table]['attributeDiffs'] = array();
				foreach($this->db[1]['tables'][$table] as $colName=>$colInfo){
					// All db2 table cols exist in db1
					//echo "\t".$colName."\n";
					if(!(array_key_exists($colName,$this->db[0]['tables'][$table]))){
						//echo "\t".$colName." missing in db1 \n";
						array_push($this->columnDiffs['db0'][$table]['missing'],$colName);
					}
					elseif(array_diff_assoc($colInfo,$this->db[0]['tables'][$table][$colName])){
						$this->columnDiffs['db0'][$table]['attributeDiffs'][$colName] = array_diff_assoc($colInfo,$this->db[0]['tables'][$table][$colName]);
					}
				}
			}
			
		}
		//print_r($this->columnDiffs);
	}
	
	private function reportDiffs()
	{
		echo "---------------- Diff Report ---------------- \n\n";
		echo " db1 dsn = ".$this->dsn1."\n";
		echo " db2 dsn = ".$this->dsn2."\n\n";
		echo " #Table Diffs\n";
		if(!(empty($this->tableDiffs['db0']))){
			foreach($this->tableDiffs['db0'] as $table){
				echo "  Table Missing from db1! -".$table. " \n";
			}
		}
		if(!(empty($this->tableDiffs['db1']))){
			foreach($this->tableDiffs['db1'] as $table){
				echo "  Table Missing from db2! -".$table."\n";
			}
		}
		echo "\n #Column Diffs\n";
		foreach($this->db[1]['tables'] as $table=>$tableInfo){
			if(!(empty($this->columnDiffs['db1'][$table]['missing']))){
				foreach($this->columnDiffs['db1'][$table]['missing'] as $col){
					echo "  Column Missing from db2 in table ".$table."! -".$col."\n";
				}
			}
			if(!(empty($this->columnDiffs['db0'][$table]['missing']))){
				foreach($this->columnDiffs['db0'][$table]['missing'] as $col){
					echo "  Column Missing from db1 in table ".$table."! -".$col."\n";
				}
			}
			if(!(empty($this->columnDiffs['db1'][$table]['attributeDiffs']))){
				foreach($this->columnDiffs['db1'][$table]['attributeDiffs'] as $colName=>$attDiffs){
					echo "  Column Attributes differ from db1 in table ".$table.", column ".$colName."! - ".implode(' ,',$attDiffs)."\n";
				}
			}
			if(!(empty($this->columnDiffs['db0'][$table]['attributeDiffs']))){
				foreach($this->columnDiffs['db0'][$table]['attributeDiffs'] as $colName=>$attDiffs){
					echo "  Column Attributes differ from db2 in table ".$table.", column ".$colName."! - ".implode(' ,',$attDiffs)."\n";
				}
			}
		}
		echo "\n\n";
	}
}




$MysqlCompare = new MysqlCompare();
?>
