<?php
require_once APPPATH.'/models/project_model.php';
class Cases_model extends Project_model{
	function __construct() {
		parent::__construct();
	}
	
	function add($data=array()){
		$this->id=parent::add($data);
		$this->addLabel($this->id, '等待立案审核');
		$this->addLabel($this->id, '案件');
		return $this->id;
	}
	
	function getClientList($project_id,$relation='客户'){
		$project_id=intval($project_id);
		
		$query="
			SELECT case_people.id,case_people.people,case_people.type,case_people.role,IF(people.abbreviation IS NULL,people.name,people.abbreviation) AS name,phone.content AS phone,email.content AS email
			FROM case_people
				INNER JOIN people ON people.id=case_people.people
				LEFT JOIN (
					SELECT people, GROUP_CONCAT(content) AS content
					FROM people_profile 
					WHERE name IN ('固定电话','电话','手机')
					GROUP BY people
				)phone ON phone.people=case_people.people
				LEFT JOIN(
					SELECT people, GROUP_CONCAT(content) AS content
					FROM people_profile
					WHERE name IN ('电子邮件')
					GROUP BY people
				)email ON email.people=case_people.people
			WHERE case_people.case=$project_id
		";
		
		if(isset($relation)){
			$query.=" AND case_people.type='$relation'";
		}
		
		return $this->db->query($query)->result_array();
	}
	
	function getStaffList($project_id){
		$project_id=intval($project_id);

		$query="
			SELECT
				case_people.id,GROUP_CONCAT(case_people.role) AS role,case_people.hourly_fee,CONCAT(TRUNCATE(SUM(case_people.contribute)*100,1),'%') AS contribute,
				staff.name AS staff_name,
				TRUNCATE(account.amount_sum*SUM(case_people.contribute),2) AS contribute_amount,
				lawyer_hour.hours_sum
			FROM 
				case_people INNER JOIN people staff ON staff.id=case_people.people AND case_people.type='律师'
				CROSS JOIN (
					SELECT SUM(amount) AS amount_sum FROM account WHERE `case` = $project_id AND name <> '办案费'
				)account
				LEFT JOIN (
					SELECT uid,SUM(IF(hours_checked IS NULL,hours_own,hours_checked)) AS hours_sum 
					FROM schedule 
					WHERE schedule.`case` = $project_id AND display=1 AND completed=1 GROUP BY uid
				)lawyer_hour
				ON lawyer_hour.uid=case_people.people
			WHERE case_people.case=$project_id
			GROUP BY case_people.people
		";
		
		return $this->db->query($query)->result_array();
	}
	
	function addStaff($project,$people,$role,$hourly_fee=NULL){
		$project=intval($project);
		$people=intval($people);
		
		if(isset($hourly_fee)){
			$hourly_fee=intval($hourly_fee);
		}
		
		$data=array(
			'case'=>$project,
			'people'=>$people,
			'role'=>$role,
			'hourly_fee'=>$hourly_fee,
			'type'=>'律师'
		);
		
		$data+=uidTime();
		
		$this->db->insert('case_people',$data);
		
		return $this->db->insert_id();
	}
	
	/**
	 * 获得一个案件的主办律师名
	 * @return string
	 */
	function getResponsibleStaffNames($case_id){
		$case_id=intval($case_id);
		
		$this->db->select("GROUP_CONCAT(people.name) names",false)
			->from('case_people')
			->join('people',"case_people.people = people.id AND case_people.role = '主办律师'",'INNER')
			->where('case_people.case',$case_id);
		
		return $this->db->get()->row()->names;
	}
	
	/**
	 * 根据案件id获得标签，进而生成描述性字符串
	 * @return string
	 */
	function getCompiledLabels($case_id){
		$case_id=intval($case_id);
		
		$this->db->select('label.id,label.name,label.order,label.color')
			->from('case_label')
			->join('label',"case_label.label = label.id",'INNER')
			->where('case_label.case',$case_id)
			->order_by('label.order','DESC');
		
		$result=$this->db->get()->result_array();
		
		$labels=array();
		
		foreach($result as $row){
			$labels[$row['name']]=$row;
		}
		
		if(isset($labels['类型已锁定']) && isset($labels['客户已锁定']) && isset($labels['职员已锁定']) && isset($labels['费用已锁定'])){
			unset($labels['类型已锁定']);unset($labels['客户已锁定']);unset($labels['职员已锁定']);unset($labels['费用已锁定']);
			$labels['已锁定']=array('name'=>'已锁定','color'=>'#080');
		}
		
		if(isset($labels['通过财务审核']) && isset($labels['通过信息审核']) && isset($labels['通过主管审核']) && isset($labels['案卷已归档'])){
			unset($labels['通过财务审核']);unset($labels['通过信息审核']);unset($labels['通过主管审核']);unset($labels['案卷已归档']);
			$labels['已归档']=array('name'=>'已归档','color'=>'#888');
		}
		
		$labels_string='<div class="chzn-container-multi"><ul class="chzn-choices">';
		
		foreach($labels as $key=>$label){
			if(!is_array(option('search/labels')) || !in_array($key,option('search/labels'))){
				$labels_string.='<li class="search-choice" style="color:'.$label['color'].'">'.$label['name'].'</li>';
			}
		}
		
		$labels_string.='</ul></div>';
		
		return $labels_string;
	}

}
?>
