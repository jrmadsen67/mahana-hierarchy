<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Mahana_hierarchy v1.0
 * Author: Jeff Madsen
 * Website: www.codebyjeff.com
 * Twitter: @codebyjeff
 *
 */


// Based on concept by Ferdy Christant
// http://ferdychristant.com/blog//archive/DOMM-7QJPM7

// A few lines of code blatantly "borrowed" from Jamie Rumbelow's MY_Model
// https://github.com/jamierumbelow/codeigniter-base-model

/*

--
-- Table structure for table `hierarchy`
--

CREATE TABLE `hierarchy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(55) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `lineage` text,
  `deep` int(3) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


*/  


class Mahana_hierarchy {

	protected $ci;

	//set this to whatever is most useful for you
	protected $_db = 'default'; 

	//set this to whatever is most useful for you
	protected $_table = 'hierarchy'; 

	//if you rename your table fields, also rename them here
	protected $primary_key = 'id';

	protected $parent_id = 'parent_id';

	protected $lineage = 'lineage';

	protected $deep = 'deep';	


    public function __construct()
    {
    	$this->ci =& get_instance(); 

    	$this->db = $this->ci->load->database($this->_db, TRUE);

    }    	


	// Fetch all records based on the primary key, ordered by their lineage. 
	// param - integer - allows you to return only from a certain point  (optional)
	// Returns result_array	
	public function get($top_id=0)
	{
		if (!empty($top_id))
		{
			$parent = $this->get_one($top_id);
			if (!empty($parent))
			{
				$this->db->like($this->lineage, $parent[$this->lineage], 'after');
			}				
		}	

        $query = $this->db->order_by($this->lineage)->get($this->_table);
        return $query->result_array();        
	}

	// Fetch a single record based on the primary key. 
	// Returns row_array
	public function get_one($id)
	{
		$row = $this->db->where($this->primary_key, $id)
                        ->get($this->_table)
                        ->row_array();
        return $row;                
	}

	// Fetch all direct child records based on the parent id, ordered by their lineage. 
	// param - integer - parent id of child records
	// Returns result_array	
	public function get_children($parent_id)
	{		
        $query = $this->db->order_by($this->lineage)->where($this->parent_id, $parent_id)->get($this->_table);
        return $query->result_array(); 
    }


	// Fetch all descendent records based on the parent id, ordered by their lineage. 
	// param - integer - parent id of descendent records
	// Returns result_array	
	public function get_descendents($parent_id)
	{		
		$parent = $this->get_one($parent_id);
		if (empty($parent)) return array();

		// note that adding '-' to the like leaves out the parent record
        $query = $this->db->order_by($this->lineage)->like($this->lineage, $parent[$this->lineage].'-', 'after')->get($this->_table);
        return $query->result_array(); 
    }

	// Fetch all descendent records based on the parent id, ordered by their lineage, and groups them as a mulit-dimensional array. 
	// param - integer - parent id of descendent records (optional)
	// Returns result_array	
    public function get_grouped_children($top_id=0)
    {

    	$result = $this->get($top_id);
    	$grouped_result = $this->_findChildren($result);

		return $grouped_result;
    }


	// inserts new record. If no parent_id included, assumes top level item
	// returns result of final statement
	public function insert($data)
    {
    	if(!empty($data[$this->parent_id]))
    	{
    		//get parent info
    		$parent = $this->get_one($data[$this->parent_id]);
    		$data[$this->deep] = $parent[$this->deep] + 1;
    	}	

    	$this->db->insert($this->_table, $data);
    	$insert_id =  $this->db->insert_id();

    	//update new record's lineage
    	$update[$this->lineage] = (empty($parent[$this->lineage]))? str_pad($insert_id, 5 ,'0', STR_PAD_LEFT): $parent[$this->lineage].'-'.str_pad($insert_id, 5, '0', STR_PAD_LEFT);

    	return $this->update($insert_id, $update);

    }	

	// updates record
	// returns update result
    public function update($id, $data)
    {
        $result = $this->db->where($this->primary_key, $id)
                           ->set($data)
                           ->update($this->_table);
        return $result;                   
    }

    // deletes record
    // param - true/false - delete all descendent records
    public function delete($id, $with_children=false)
    {

    	//little clumsy, due to some Active Record restrictions

    	if ($with_children)
    	{
    		$parent = $this->get_one($id);
    	}	

		$this->db->like('id', $id, 'none');
    	if (!empty($parent) && $with_children)
    	{    		
    		$this->db->or_like($this->lineage, $parent[$this->lineage].'-', 'after');
    	}	
    	
		$this->db->delete($this->_table); 

    }

    // gets the maximum depth of any branch of tree
    // returns integer
    public function max_deep()
    {
    	$row = $this->db->select_max($this->deep, 'max_deep')->get($this->_table)->row_array();
    	return $row['max_deep'] + 1; //deep starts at 0
    }

    //for use when the data is existing & has parent_id, but no lineage or deep
    //can be used to repair your data or set it up the first time
    function resync()
    {
        //we could probably just re-write this with two copies of your table, and update. I think this will run safer and leave less to worry
        $current_data = $this->db->select($this->primary_key. ', ' . $this->parent_id)->order_by($this->parent_id, 'asc')->get($this->_table)->result_array();

        if (!empty($current_data))
        {
            foreach ($current_data as $row) {

                $update[$this->deep] = 0;

                if(!empty($row[$this->parent_id]))
                {
                    //get parent info
                    $parent = $this->get_one($row[$this->parent_id]);
                    $update[$this->deep] = $parent[$this->deep] + 1;
                }                   

                $update[$this->lineage] = (empty($parent[$this->lineage]))? str_pad($row[$this->primary_key], 5 ,'0', STR_PAD_LEFT): $parent[$this->lineage].'-'.str_pad($row[$this->primary_key], 5, '0', STR_PAD_LEFT);
                $this->update($row[$this->primary_key], $update); 
                unset($parent);
            }
        }

    }


    // Thank you, http://stackoverflow.com/users/427328/elusive
	function _findChildren(&$nodeList, $parentId = null) {
	    $nodes = array();

	    foreach ($nodeList as $node) {
	        if ($node[$this->parent_id] == $parentId) {
	            $node['children'] = $this->_findChildren($nodeList, $node[$this->primary_key]);
	            $nodes[] = $node;
	        }
	    }

	    return $nodes;
	}


}	
