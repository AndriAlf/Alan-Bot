<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Tebakkode_m extends CI_Model {

  function __construct(){
    parent::__construct();
    $this->load->database();
  }

  // Events Log
  function log_events($signature, $body)
  {
    $this->db->set('signature', $signature)
    ->set('events', $body)
    ->insert('eventlog');

    return $this->db->insert_id();
  }

  // Get Member Data
  function getMember($userId){
    $data = $this->db->where('member_id', $userId)->get('members')->row_array();
    if(count($data)>0) return $data;
    return false;
  }

  // Get Member in Divisi
  function getDiv($div){
    $data = $data = $this->db->where('divisi', $div)->get('members')->result_array();
    if(count($data)>0) return $data;
    return false;
  }

  // Get Member in Call
  function getCall($call){
    $data = $data = $this->db->where('call_name', $call)->get('members')->result_array();
    if(count($data)>0) return $data;
    return false;
  }

  // GET SEMUA NAMA DIVISI
  function getDivName(){
    $data = $data = $this->db->select('div_name')->get('division')->result_array();
    if(count($data)>0) return $data;
    return false;
  }

  // GET SEMUA TIPE PRODUK
  function getTypeName(){
    $data = $data = $this->db->get('products')->result_array();
    if(count($data)>0) return $data;
    return false;
  }

  //GET ALL
  function getAllMember(){
    $data = $data = $this->db->select('member_id')->get('members')->result_array();
    if(count($data)>0) return $data;
    return false;
  }

  //GET ALL ISSUE
  function getAllIssue(){
    $data = $data = $this->db->get('issue')->result_array();
    if(count($data)>0) return $data;
    return false;
  }

  //GET COND ISSUE
  function getCondIssue($where){
    $data = $data = $this->db->where($where)->get('issue')->result_array();
    if(count($data)>0) return $data;
    return false;
  }

  //GET ONE ISSUE BY TITLE
  function getOneIssue($name){
    $data = $data = $this->db->where('issue_name', $name)->get('issue')->row_array();
    if(count($data)>0) return $data;
    return false;
  }

  //GET ALL ISSUE IN TYPE
  function getTypeIssue($type){
    $data = $data = $this->db->where('issue_type', $type)->get('issue')->result_array();
    if(count($data)>0) return $data;
    return false;
  }

  //GET ALL CALL NAME
  function getCallMember(){
    $data = $data = $this->db->select('call_name')->get('members')->result_array();
    if(count($data)>0) return $data;
    return false;
  }

  //GET EDITOR CALL NAME
  function getEcallMember(){
    $data = $data = $this->db->select('call_name')->where('divisi','EDITOR')->get('members')->result_array();
    if(count($data)>0) return $data;
    return false;
  }

  // Save MEMBER DATA
  function saveMember($profile){
    $this->db->set('member_id', $profile['userId'])->set('line_name', $profile['displayName'])->insert('members');
    return $this->db->insert_id();
  }

  // SET DIVISI
  function setDiv($name){
    $this->db->set('div_name', $name)->insert('division');
    return $this->db->insert_id();
  }

  // SET TIPE PRODUCT
  function setProduct($type){
    $this->db->set('prod_id', $type)->insert('products');
    return $this->db->insert_id();
  }

  // SET ISSUE
  function setIssue($type, $pj, $name, $by){
    $this->db->set('issue_type', $type)->set('issue_holder', $pj)->set('issue_name', $name)->set('issue_stats', 'Pending')->set('issue_date', date('Y-m-d'))->set('issue_by', $by)->insert('issue');
    return $this->db->insert_id();
  }

  // SET DIVISI MEMBER
  function setDivisi($userId, $div){
    $this->db->set('divisi', $div)->where('member_id', $userId)->update('members');
    return $this->db->affected_rows();
  }

  // SET DIVISI OTHER MEMBER
  function setODivisi($name, $div){
    $this->db->set('divisi', $div)->where('call_name', $name)->update('members');
    return $this->db->affected_rows();
  }

  // SET CALL NAME
  function setName($userId, $name){
    $this->db->set('call_name', $name)->where('member_id', $userId)->update('members');
    return $this->db->affected_rows();
  }

  // SET STATS NAME
  function setStatsName($name){
    $this->db->set('istats_id', $name)->insert('istats');
    return $this->db->affected_rows();
  }

  // SET ISSUE STATUS
  function setIssueStatus($stats, $name){
    $this->db->set('issue_stats', $stats)->where('issue_name', $name)->update('issue');
    return $this->db->affected_rows();
  }

  // SET ISSUE HOLDER
  function setIssueHolder($pj, $name){
    $this->db->set('issue_holder', $pj)->where('issue_name', $name)->update('issue');
    return $this->db->affected_rows();
  }

  // SET ISSUE HOLDER2
  function setIssueHolder2($pj, $name){
    $this->db->set('issue_holder2', $pj)->where('issue_name', $name)->update('issue');
    return $this->db->affected_rows();
  }

  // SET ISSUE EDITOR
  function setIssueEditor($editor, $name){
    $this->db->set('issue_editor', $editor)->where('issue_name', $name)->update('issue');
    return $this->db->affected_rows();
  }

  // SET ISSUE PUB TIME
  function setPubTime($name){
    $this->db->set('issue_endate', date('Y-m-d'))->where('issue_name', $name)->update('issue');
    return $this->db->affected_rows();
  }

  // DELETE DIVISI MEMBER
  function leaveDivisi($key){
    $this->db->set('divisi',null)->where('member_id', $key)->update('members');
    return $this->db->affected_rows();
  }

  // DELETE PRODUCT TYPE
  function delProduct($type){
    $this->db->where('prod_id', $type)->delete('products');
    return $this->db->affected_rows();
  }

  // DELETE DIVISI
  function delDiv($name){
    $this->db->where('div_name', $name)->delete('division');
    return $this->db->affected_rows();
  }

  // DELETE Issue
  function delIssue($name){
    $this->db->where('issue_name', $name)->delete('issue');
    return $this->db->affected_rows();
  }


}
