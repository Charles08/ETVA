<?php

class EtvaNode extends BaseEtvaNode
{
    const NAME_MAP = 'name';
    const RESERVED_MEM_MB = 768;
    const NODE_ACTIVE = 1;
    const NODE_INACTIVE = 0;
    const NODE_FAIL = -1;
    const NODE_FAIL_UP = -11;
    const NODE_MAINTENANCE = -2;
    const NODE_MAINTENANCE_UP = -21;
    const NODE_COMA = -3;
    const INITIALIZE_OK = 'ok';
    const INITIALIZE_PENDING = 'pending';

    public function initData($arr)
	{
        if(array_key_exists(self::NAME_MAP, $arr)) $this->setName($arr[self::NAME_MAP]);        

	}

    /*
     * sets last message received. overwrites if any already exists
     */
    public function setErrorMessage($action, $message = null)
    {
        if(!$message) $message = EtvaNodePeer::_PROBLEM_;
        
        $data = array('message' => $message, 'priority' =>EtvaEventLogger::ERR, 'action' =>$action);
        
        $this->setLastMessage(json_encode($data));
        $this->save();
    }

    /*
     * clears message of action if exists
     */
    public function clearErrorMessage($action)
    {
        if($this->isLastErrorMessage($action)){
            $this->setLastMessage('');
            $this->save();
        }
    }

    public function isLastErrorMessage($action)
    {
        $last_message = $this->getLastMessage();
        $last_message_decoded = json_decode($last_message,true);
        
        if(isset($last_message_decoded['action']) && ($last_message_decoded['action']==$action)){
            return true;
        }
        return false;
    }


    public function getServers($query = null)
    {
        return $this->getEtvaServers($query);
    }
    public function getEtvaServers($query = null)
    {
        if( !$query ) $query = EtvaServerQuery::create();
        return $query->useEtvaServerAssignQuery('ServerAssign','RIGHT JOIN')
                        ->filterByNodeId($this->getId())
                    ->endUse()
                    ->find();


    }

    /*
     * returns nodes from the same cluster as this
     */
    public function getNodesCluster(Criteria $criteria)
    {
        if(!$criteria) $criteria = new Criteria();
        $criteria->add(EtvaNodePeer::CLUSTER_ID, $this->getClusterId());
        return EtvaNodePeer::doSelect($criteria);
    }

    public function getEtvaLogicalvolumes(Criteria $criteria = null)
    {

		if ($criteria === null) {
			$criteria = new Criteria();
		}		
        
        $criteria->add(EtvaNodeLogicalvolumePeer::NODE_ID, $this->getId());
        $criteria->addAnd(EtvaLogicalvolumePeer::LV,'etva-isos',Criteria::NOT_EQUAL);
        $criteria->addAnd(EtvaLogicalvolumePeer::LV,'etva_isos',Criteria::NOT_EQUAL);
        $criteria->addAnd(EtvaLogicalvolumePeer::LV,'etvaisos',Criteria::NOT_EQUAL);
        $criteria->addJoin(EtvaNodeLogicalvolumePeer::LOGICALVOLUME_ID, EtvaLogicalvolumePeer::ID);        
        $criteria->addAscendingOrderByColumn(EtvaLogicalvolumePeer::LV);

        return EtvaLogicalvolumePeer::doSelect($criteria);
    }
 


    public function __toString()
    {
        return $this->getName();
    }

    public function setSoapTimeout($val) //seconds
    {
        $this->rcv_timeout = $val;

    }


    /*
     * Send soap request to VirtAgent
     * It onlys send request id state is 1. Relies on virtAgent to send state=1
     * If virtAgent state is 0 and forceRequest = false does not send request
     * If forceRequest flag is true the request will always be sent.
     * If virtAgent request is sent and returns TCP failure sets state to 0
     */
    public function soapSend($method,$params=null,$forceRequest=false,$rcv_timeout=0){
        $dispatcher = sfContext::getInstance()->getEventDispatcher();
        $addr = $this->getIP();
        $port = $this->getPort();
        $proto = "tcp";
        $host = "" . $proto . "://" . $addr . ":" . $port;
        $state = $this->getState();
        $agent = $this->getName();

        if(!$params) $params = array("nil"=>"true");
        $request_array = array('request'=>array(
                            'agent'=>$agent,
                            'host'=>$host,
                            'port'=>$port,
                            'method'=>$method,
                            'params'=>$params));                

        if(!$state && !$forceRequest){
            /*
             * if current state is 0 DO NOT send soap request
             * this approach avoids generating unnecessary traffic since if
             *  the agent is alive it should update its state
             *
             */
            // if current state is 0 DO NOT send soap request
            $info = sfContext::getInstance()->getI18N()->__('VirtAgent down! Request not sent!');
            $error = sfContext::getInstance()->getI18N()->__('VirtAgent down');
            $response = array('success'=>false,'agent'=>$this->getName(),'error'=>$error,'info'=>$info);
        }else{
            //if state reports 1 send request....
            $soap = new soapClient_($host,$port);
            if($rcv_timeout) $soap->set_rcv_timeout($rcv_timeout);
            else if($this->rcv_timeout) $soap->set_rcv_timeout($this->rcv_timeout);
            $response = $soap->processSoap($method, $params);
            $response['agent'] = $this->getName();


            /*
             * if response is TCP failure then VirtAgent is not reachable
             * set state to 0
             */
            if(isset($response['faultcode']) && $response['faultcode']=='TCP')
            {
                if( $this->getState() >= self::NODE_INACTIVE ){
                    $this->setState(self::NODE_INACTIVE);
                }
                $this->save();
            }else{

                //keepalive update
                if( $this->getState() >= self::NODE_INACTIVE ){
                    $this->setState(self::NODE_ACTIVE);
                }
                $this->setLastKeepalive('NOW');
                $this->save();
            }

        }

        $response_array = array('response'=>$response);
        
        $all_params = array_merge($request_array,$response_array);

        $dispatcher->notify(new sfEvent($this, sfConfig::get('app_virtsoap_log'),$all_params));

        return $response;
        
    }

    public function retrievePhysicalvolumeByPv($pv){
        $criteria = new Criteria();
        $criteria->add(EtvaNodePhysicalvolumePeer::NODE_ID, $this->getId());
        $criteria->addJoin(EtvaNodePhysicalvolumePeer::PHYSICALVOLUME_ID, EtvaPhysicalvolumePeer::ID);
        $criteria->add(EtvaPhysicalvolumePeer::PV, $pv);
        
        return EtvaPhysicalvolumePeer::doSelectOne($criteria);
    }

    public function retrievePhysicalvolumeByUuid($uuid){
        $criteria = new Criteria();
        $criteria->add(EtvaPhysicalvolumePeer::UUID, $uuid);

        return EtvaPhysicalvolumePeer::doSelectOne($criteria);
    }


    /*
     * retrieves node physical volume info with matching current node and device
     */
    public function retrievePhysicalvolumeByDevice($dev){
        $criteria = new Criteria();
        $criteria->add(EtvaNodePhysicalvolumePeer::NODE_ID, $this->getId());
        $criteria->add(EtvaNodePhysicalvolumePeer::DEVICE, $dev);
        $criteria->addJoin(EtvaNodePhysicalvolumePeer::PHYSICALVOLUME_ID, EtvaPhysicalvolumePeer::ID);

        return EtvaPhysicalvolumePeer::doSelectOne($criteria);
    }

    public function retrieveVolumegroupByVg($vg){
        $criteria = new Criteria();
        $criteria->add(EtvaNodeVolumegroupPeer::NODE_ID, $this->getId());
        $criteria->addJoin(EtvaNodeVolumegroupPeer::VOLUMEGROUP_ID, EtvaVolumegroupPeer::ID);
        $criteria->add(EtvaVolumegroupPeer::VG, $vg);

        return EtvaVolumegroupPeer::doSelectOne($criteria);
    }
    public function retrieveVolumegroupByUuid($uuid){
        $criteria = new Criteria();
        $criteria->add(EtvaNodeVolumegroupPeer::NODE_ID, $this->getId());
        $criteria->addJoin(EtvaNodeVolumegroupPeer::VOLUMEGROUP_ID, EtvaVolumegroupPeer::ID);
        $criteria->add(EtvaVolumegroupPeer::UUID, $uuid);

        return EtvaVolumegroupPeer::doSelectOne($criteria);
    }

    public function retrieveLogicalvolumeByLv($lv){

        $criteria = new Criteria();
        $criteria->add(EtvaNodeLogicalvolumePeer::NODE_ID, $this->getId());
        $criteria->addJoin(EtvaNodeLogicalvolumePeer::LOGICALVOLUME_ID, EtvaLogicalvolumePeer::ID);
        $criteria->add(EtvaLogicalvolumePeer::LV, $lv);

        return EtvaLogicalvolumePeer::doSelectOne($criteria);
    }
    public function retrieveLogicalvolumeByLvDevice($lv){

        $criteria = new Criteria();
        $criteria->add(EtvaNodeLogicalvolumePeer::NODE_ID, $this->getId());
        $criteria->addJoin(EtvaNodeLogicalvolumePeer::LOGICALVOLUME_ID, EtvaLogicalvolumePeer::ID);
        $criteria->add(EtvaLogicalvolumePeer::LVDEVICE, $lv);

        return EtvaLogicalvolumePeer::doSelectOne($criteria);
    }
    public function retrieveLogicalvolumeByVgLv($vg,$lv){

        $criteria = new Criteria();
        $criteria->add(EtvaNodeLogicalvolumePeer::NODE_ID, $this->getId());
        $criteria->add(EtvaLogicalvolumePeer::LV, $lv);
        $criteria->add(EtvaVolumegroupPeer::VG, $vg);
        $criteria->addJoin(EtvaNodeLogicalvolumePeer::LOGICALVOLUME_ID, EtvaLogicalvolumePeer::ID);
        $criteria->addJoin(EtvaLogicalvolumePeer::VOLUMEGROUP_ID, EtvaVolumegroupPeer::ID);

        return EtvaLogicalvolumePeer::doSelectOne($criteria);
    }
    public function retrieveLogicalvolumeByUuid($uuid){

        $criteria = new Criteria();
        $criteria->add(EtvaNodeLogicalvolumePeer::NODE_ID, $this->getId());
        $criteria->addJoin(EtvaNodeLogicalvolumePeer::LOGICALVOLUME_ID, EtvaLogicalvolumePeer::ID);
        $criteria->add(EtvaLogicalvolumePeer::UUID, $uuid);

        return EtvaLogicalvolumePeer::doSelectOne($criteria);
    }
    public function retrieveLogicalvolume($uuid = null, $vg = null, $lv = null){
        if( $uuid )
            return $this->retrieveLogicalvolumeByUuid($uuid);
        else if( $vg && $lv )
            return $this->retrieveLogicalvolumeByVgLv($vg,$lv);
        else
            return $this->retrieveLogicalvolumeByLv($lv);
    }
    public function retrieveLogicalvolumeByAny($lv, $vg = null, $uuid = null)
    {
        $etva_lv = $this->retrieveLogicalvolumeByUuid($lv);
        if( !$etva_lv )
        {
            if( $uuid ){
                $etva_lv = $this->retrieveLogicalvolumeByUuid($uuid);               // by uuid
            } else if( $vg ) {
                $etva_lv = $this->retrieveLogicalvolumeByVgLv($vg,$lv);             // by vg and lv
            } else {
                $etva_lv = $this->retrieveLogicalvolumeByLvDevice($lv);             // by device 
                if( !$etva_lv ) $etva_lv = $this->retrieveLogicalvolumeByLv($lv);   // by name
            }
        }
        return $etva_lv;
    }

    public function retrieveServerByName($server){
        /*$criteria = new Criteria();
        $criteria->add(EtvaServerPeer::NODE_ID, $this->getId());

        return EtvaServerPeer::retrieveByName($server, $criteria);*/
        return EtvaServerQuery::create()
                        ->filterByName($server)
                        ->useEtvaServerAssignQuery('ServerAssign','RIGHT JOIN')
                            ->filterByNodeId($this->getId())
                        ->endUse()
                        ->findOne();
    }

    /*
     * gets array of fields names in DB
     */
    public function toDisplay()
	{

        $array_data = $this->toArray(BasePeer::TYPE_FIELDNAME);
        $array_data['mem_text'] = Etva::Byte_to_MBconvert($array_data['memtotal']);
        $array_data['mem_available'] = Etva::Byte_to_MBconvert($array_data['memfree']);

        if( $fencingconf = $this->getFencingconf() ){
            $fencingconf_arr = (array)json_decode($fencingconf);
            foreach($fencingconf_arr as $f=>$v){
                $fk = "fencingconf_" . $f;
                $array_data[$fk] = $v;
            }
        }


        switch($array_data['state']){
            case self::NODE_ACTIVE :      $array_data['state_text'] = sfContext::getInstance()->getI18N()->__('Up');
                                          break;
            case self::NODE_INACTIVE :    $array_data['state_text'] = sfContext::getInstance()->getI18N()->__('Down');
                                          break;
            case self::NODE_FAIL_UP :
            case self::NODE_FAIL :        $array_data['state_text'] = sfContext::getInstance()->getI18N()->__('Fail');
                                          break;
            case self::NODE_MAINTENANCE_UP :
            case self::NODE_MAINTENANCE : $array_data['state_text'] = sfContext::getInstance()->getI18N()->__('Maintenance');
                                          break;
        }
		
		return $array_data;       
	}
    
    /*
     * update memfree based on total of system mem and vms mem
     */
    public function updateMemFree()
    {        
        /*$criteria = new Criteria();
        $criteria->add(EtvaServerPeer::NODE_ID,$this->getId());
        $criteria->add(EtvaServerPeer::VM_STATE,'stop',Criteria::NOT_EQUAL);    // count vms that not stopped
        $total_vms = EtvaServerPeer::getTotalMem($criteria);*/
        $servers = EtvaServerQuery::create()
                        ->filterByVmState(EtvaServer::STATE_STOP,Criteria::NOT_EQUAL)
                        ->useEtvaServerAssignQuery('ServerAssign','RIGHT JOIN')
                            ->filterByNodeId($this->getId())
                        ->endUse()
                        ->find();
        $total_vms = 0;
        foreach($servers as $i => $server){
            $total_vms += $server->getMem();
        }
        $total_vms = Etva::MB_to_Byteconvert($total_vms);
        $sys_mem = Etva::MB_to_Byteconvert(self::RESERVED_MEM_MB);
        $mem_free = $this->getMemtotal()-$sys_mem-$total_vms;
        $this->setMemfree($mem_free);
    }

    public function getMaxMem()
    {
        $sys_mem = Etva::MB_to_Byteconvert(self::RESERVED_MEM_MB);
        $max_mem = $this->getMemtotal()-$sys_mem;
        return $max_mem;
    }

    /*
     * before delete node from db delete other info...
     */
    public function preDelete(PropelPDO $con = null)
    {

        /*
         * delete lvs that are not shared....numVgs=1 only
         *
         */
        $criteria = new Criteria();       
        $criteria->add(EtvaLogicalvolumePeer::CLUSTER_ID, $this->getClusterId());
        $criteria->addGroupByColumn(EtvaNodeLogicalvolumePeer::LOGICALVOLUME_ID);
        $criteria->addAsColumn('numLvs', 'COUNT('.EtvaNodeLogicalvolumePeer::LOGICALVOLUME_ID.')');
        $criteria->addHaving($criteria->getNewCriterion(EtvaNodeLogicalvolumePeer::LOGICALVOLUME_ID, 'numLvs=1',Criteria::CUSTOM));

        $records = EtvaNodeLogicalvolumePeer::doSelectJoinEtvaLogicalvolume($criteria);

        foreach ($records as $record)
        {
            $etva_lv = $record->getEtvaLogicalvolume();
            if($record->getNodeId() == $this->getId()) $etva_lv->delete();
        }
        


        /*
         * delete vgs that are not shared....numVgs=1 only
         *
         */
        $criteria = new Criteria();
        $criteria->add(EtvaVolumegroupPeer::CLUSTER_ID, $this->getClusterId());
        $criteria->addGroupByColumn(EtvaNodeVolumegroupPeer::VOLUMEGROUP_ID);
        $criteria->addAsColumn('numVgs', 'COUNT('.EtvaNodeVolumegroupPeer::VOLUMEGROUP_ID.')');
        $criteria->addHaving($criteria->getNewCriterion(EtvaNodeVolumegroupPeer::VOLUMEGROUP_ID, 'numVgs=1',Criteria::CUSTOM));
        $records = EtvaNodeVolumegroupPeer::doSelectJoinEtvaVolumegroup($criteria);

        foreach ($records as $record)
        {
            $etva_vg = $record->getEtvaVolumegroup();
            if($record->getNodeId() == $this->getId()) $etva_vg->delete();
        }


        /*
         * delete pvs that are not shared....numVgs=1 only
         *
         */
        $criteria = new Criteria();
        $criteria->add(EtvaPhysicalvolumePeer::CLUSTER_ID, $this->getClusterId());
        $criteria->addGroupByColumn(EtvaNodePhysicalvolumePeer::PHYSICALVOLUME_ID);
        $criteria->addAsColumn('numPvs', 'COUNT('.EtvaNodePhysicalvolumePeer::PHYSICALVOLUME_ID.')');
        $criteria->addHaving($criteria->getNewCriterion(EtvaNodePhysicalvolumePeer::PHYSICALVOLUME_ID, 'numPvs=1',Criteria::CUSTOM));
        $records = EtvaNodePhysicalvolumePeer::doSelectJoinEtvaPhysicalvolume($criteria);

        foreach ($records as $record)
        {
            $etva_pv = $record->getEtvaPhysicalvolume();
            if($record->getNodeId() == $this->getId()) $etva_pv->delete();
        }

        // delete rra node dir and cpu load rrd
        $this->deleteRRAFiles();        

        return true;
    }



    public function deleteRRAFiles()
    {
        
        $node_uuid = $this->getUuid();

        $cpu_load = new NodeLoadRRA($node_uuid,false);
        $cpu_load->delete(true); // true == remove dir also
        
    }


    /*
     * removes node pvs, vgs and lvs
     */
    public function clearStorage()
    {
        $c = new Criteria();
        $c->add(EtvaNodeLogicalvolumePeer::NODE_ID, $this->getId());
        EtvaLogicalvolumePeer::doDelete($c);

        $c = new Criteria();
        $c->add(EtvaNodeVolumegroupPeer::NODE_ID, $this->getId());
        EtvaVolumegroupPeer::doDelete($c);

        $c = new Criteria();
        $c->add(EtvaNodePhysicalvolumePeer::NODE_ID, $this->getId());
        EtvaPhysicalvolumePeer::doDelete($c);

    }

    /*
     * returns nodes from the same cluster as this
     */
    public static function getFirstActiveNode(EtvaCluster $cluster)
    {
        $c = new Criteria();
        $c->add(EtvaNodePeer::CLUSTER_ID, $cluster->getId(), Criteria::EQUAL);
        $c->addAnd(EtvaNodePeer::STATE, EtvaNode::NODE_ACTIVE, Criteria::EQUAL);
        $c->addDescendingOrderByColumn(EtvaNodePeer::ID);
        $c->setLimit(1);
        $etva_node = EtvaNodePeer::doSelectOne($c);
        return $etva_node;
    }

    public function hasPvs(){
        $disk_pvs = $this->getEtvaNodePhysicalvolumesJoinEtvaPhysicalvolume();
        return ((!count($disk_pvs)) ? false: true);
    }
    public function hasVgs(){
        $c = new Criteria();
        //$c->add(EtvaVolumegroupPeer::VG,sfConfig::get('app_volgroup_disk_flag'));
        $disk_vgs = $this->getEtvaNodeVolumegroupsJoinEtvaVolumegroup($c);
        return ((!count($disk_vgs)) ? false: true);
    }
    public function canCreateVms(){
        return (!$this->getIsSpareNode() && ($this->getState()==self::NODE_ACTIVE) && ($this->getInitialize()==self::INITIALIZE_OK) && $this->hasVgs());
    }
    public function isNodeFree(){
        $servers = $this->getEtvaServers();
        return count($servers) ? false : true;
    }

    public function getFencingconf_cmd($action=null,$fencingconf_json=null){
        if( !$fencingconf_json ) $fencingconf_json = $this->getFencingconf();

        if( $fencingconf_json ){
            $fc_obj = (array)json_decode($fencingconf_json);
            if( $fc_obj['type'] ){
                $fencingtypes_cmds = sfConfig::get('app_fencingcmds');

                # add execution path
                # TODO improve this
                $fencing_cmd = "/sbin/".$fc_obj['type'];

                $fencing_cmd .= " -a ".$fc_obj['address'];
                $fencing_cmd .= " -l ".$fc_obj['username'];
                if( $fc_obj['password'] ){
                    $fencing_cmd .= " -p ".$fc_obj['password'];
                }
                if( $fc_obj['plug'] || isset($fencingtypes_cmds['datacenter'][$fc_obj['type']]) ){
                    $plug = $fc_obj['plug'] ? $fc_obj['plug']: $this->getName(); 
                    $fencing_cmd .= " -n ". $plug;
                }
                if( $action ){
                    $fencing_cmd .= " -o $action";
                } else if( $fc_obj['action'] ){
                    $fencing_cmd .= " -o ".$fc_obj['action'];
                }
                if( $fc_obj['secure'] ){
                    $identity_file = sfConfig::get('app_sshkey_privfile');
                    if( file_exists($identity_file) ){
                        $fencing_cmd .= " -k ". $identity_file;
                    }
                }

                return $fencing_cmd;
            }
        }
    }

    public function getIsUp(){
        return ( ( $this->getState() == NODE_ACTIVE ) || 
                    ( $this->getState() == NODE_FAIL_UP ) || 
                    ( $this->getState() == NODE_MAINTENANCE_UP ) ) ? true :false;
    }

    public function canAssignServer( EtvaServer $server ){
        $server_mem = Etva::MB_to_Byteconvert($server->getMem());
        if( ($this->getCputotal() >= $server->getVcpu()) &&
            ($this->getMaxMem() > $server_mem) &&
            !$server->getDevices_VA() &&
            !$server->getHasSnapshots() ){
            if (($server->getVmState() !== EtvaServer::RUNNING) ||
                    ($this->getMemfree() > $server_mem)){
                error_log("canAssignServer OK node=".$this->getName()." server=".$server->getName()." vmstate=".$server->getVmState()." maxmem=".$this->getMaxMem()." memfree=".$this->getMemfree()." server_mem=".$server_mem);
                return true;
            }
        }
        error_log("canAssignServer NOK node=".$this->getName()." server=".$server->getName()." vmstate=".$server->getVmState()." maxmem=".$this->getMaxMem()." memfree=".$this->getMemfree()." server_mem=".$server_mem);
        return false;
    }

    # get servers assign to node with guest agent installed
    public function getServersWithGA(){
        $servers = EtvaServerQuery::create()
            ->filterByVmState(EtvaServer::RUNNING)
            ->filterByGaState(EtvaServerPeer::_GA_UNINSTALLED_,Criteria::NOT_EQUAL)
            ->useEtvaServerAssignQuery('ServerAssign','RIGHT JOIN')
                ->useEtvaNodeQuery()
                    ->filterByState(EtvaNode::NODE_ACTIVE)  // only if node is active
                    ->filterById($this->getId())
                ->endUse()
            ->endUse()
            ->find();
        return $servers;
    }
}
