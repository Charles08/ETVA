<?php
/*
 * class to perform server operations with VA
 */
class EtvaServer_VA
{
    const SERVER_CREATE = 'create_vm';
    const SERVER_EDIT = 'reload_vm';
    const SERVER_REMOVE = 'vmDestroy';
    const SERVER_MIGRATE = 'migrate_vm';
    const SERVER_MOVE = 'move';
    const SERVER_GET = 'get_vm';

    private $etva_server;
    private $collNetworks;
    private $collDisks;

    public function EtvaServer_VA(EtvaServer $etva_server)
    {
        if($etva_server) $this->etva_server = $etva_server;
        $this->collNetworks = array();
        $this->collDisks = array();
    }


    public function send_remove(EtvaNode $etva_node, $keep_fs)
    {
        $method = self::SERVER_REMOVE;
        $etva_server = $this->etva_server;

        $params = array('uuid'=>$etva_server->getUuid(),'keep_fs' =>$keep_fs);
        $response = $etva_node->soapSend($method,$params);

        $result = $this->processRemoveResponse($etva_node,$response,$method,$keep_fs);
        return $result;
    }


    public function processRemoveResponse($etva_node,$response,$method, $keep_fs)
    {
        $etva_server = $this->etva_server;
        $server_name = $etva_server->getName();

        if(!$response['success']){

            $result = $response;
            $msg_i18n = sfContext::getInstance()->getI18N()->__(EtvaServerPeer::_ERR_REMOVE_,array('%name%'=>$server_name,'%info%'=>$response['info']));
            $result['error'] = $msg_i18n;

            //notify event log
            $message = Etva::getLogMessage(array('name'=>$server_name,'info'=>$response['info']), EtvaServerPeer::_ERR_REMOVE_);
            sfContext::getInstance()->getEventDispatcher()->notify(
                new sfEvent($response['agent'], 'event.log',
                    array('message' => $message,'priority'=>EtvaEventLogger::ERR)));

            return  $result;

        }

        $response_decoded = (array) $response['response'];
        $returned_status = $response_decoded['_okmsg_'];

        $server_lvs = $etva_server->getEtvaLogicalvolumes();
        $has_shared_lvs = $etva_server->hasSharedLogicalvolume();

        switch($method){
            case self::SERVER_REMOVE :
                                        $etva_server->deleteServer($keep_fs);
                                        break;
            case self::SERVER_MOVE :
                                        $cur_avail = $etva_node->getMemfree();
                                        $cur_free = $cur_avail + Etva::MB_to_Byteconvert($etva_server->getMem());
                                        $etva_node->setMemfree($cur_free);
                                        $etva_node->save();
                                        break;
            default :
                                        break;

        }                    
        
        /*
         * if has an shared lv send bulk update to nodes...
         */
        if($has_shared_lvs && !$keep_fs){
            $lv_va = new EtvaLogicalvolume_VA();
            $lv_va->send_update($etva_node);
        }


        $msg_i18n = sfContext::getInstance()->getI18N()->__(EtvaServerPeer::_OK_REMOVE_,array('%name%'=>$server_name,'%info%'=>$returned_status));

        $result = $response;
        $result['response'] = $msg_i18n;

        //notify event log
        $infos = array();
        foreach($server_lvs as $lv)
            $infos[] = Etva::getLogMessage(array('name'=>$lv->getLv()), $keep_fs ? EtvaLogicalvolumePeer::_OK_KEEP_ : EtvaLogicalvolumePeer::_OK_NOTKEEP_);

        $message = Etva::getLogMessage(array('name'=>$server_name,'info'=> implode($infos)), EtvaServerPeer::_OK_REMOVE_);
        sfContext::getInstance()->getEventDispatcher()->notify(
            new sfEvent($response['agent'], 'event.log',
                array('message' => $message)));


        return  $result;

    }

    public function send_create(EtvaNode $etva_node, $server_data)
    {
        $method = self::SERVER_CREATE;
        $etva_server = $this->etva_server;
        $etva_server->fromArray($server_data,BasePeer::TYPE_FIELDNAME);

        $check_nics_available = $this->check_nics_availability($etva_node, $server_data['networks'],$method);
        //error processing parameters
        if(!$check_nics_available['success']) return $check_nics_available;

        $check_disks_available = $this->check_disks_availability($etva_node, $server_data['disks'], $method);
        //error processing parameters
        if(!$check_disks_available['success']) return $check_disks_available;


        $mem_available = $etva_node->getMemfree();
        $server_mem = Etva::MB_to_Byteconvert($server_data['mem']);
        if($mem_available < $server_mem){
            $msg = Etva::getLogMessage(array('name' => $etva_node->getName(), 'info' => $server_mem ), EtvaNodePeer::_ERR_MEM_AVAILABLE_);
            $msg_i18n = sfContext::getInstance()->getI18N()->__(EtvaNodePeer::_ERR_MEM_AVAILABLE_,array('%name%' => $etva_node->getName(), '%info%' => $server_mem));

            return array('success'=>false,'agent'=>$etva_node->getName(),'info'=>$msg_i18n,'error'=>$msg_i18n);
        }


        $this->buildServer($method);

        $params = $etva_server->_VA();
        

        $response = $etva_node->soapSend($method,$params);
        $result = $this->processCreateResponse($etva_node,$response);
        return $result;
    }

    public function processCreateResponse($etva_node,$response)
    {
        $etva_server = $this->etva_server;
        $server_name = $etva_server->getName();

        if(!$response['success']){

            $error_decoded = $response['error'];

            $result = $response;

            $msg_i18n = sfContext::getInstance()->getI18N()->__(EtvaServerPeer::_ERR_CREATE_,array('%name%'=>$server_name,'%info%'=>$error_decoded));
            $result['error'] = $msg_i18n;

            //notify event log
            $message = Etva::getLogMessage(array('name'=>$server_name,'info'=>$response['info']), EtvaServerPeer::_ERR_CREATE_);
            sfContext::getInstance()->getEventDispatcher()->notify(
                new sfEvent($response['agent'], 'event.log',
                    array('message' => $message,'priority'=>EtvaEventLogger::ERR)));

            return  $result;

        }

        $response_decoded = (array) $response['response'];
        $returned_status = $response_decoded['_okmsg_'];
        $returned_object = (array) $response_decoded['_obj_'];

        //update some data from agent response
        $etva_server->initData($returned_object);

        $etva_server->setEtvaNode($etva_node);
        $cur_avail = $etva_node->getMemfree();
        $cur_free = $cur_avail - Etva::MB_to_Byteconvert($etva_server->getMem());
        $etva_node->setMemfree($cur_free);
        $etva_server->save();


        $msg_i18n = sfContext::getInstance()->getI18N()->__(EtvaServerPeer::_OK_CREATE_,array('%name%'=>$server_name));
        $message = Etva::getLogMessage(array('name'=>$server_name), EtvaServerPeer::_OK_CREATE_);
        sfContext::getInstance()->getEventDispatcher()->notify(new sfEvent($etva_node->getName(), 'event.log', array('message' => $message)));

        $msg = $returned_status . ' ('.$server_name.')';

        $result = array('success'=>true,
                        'agent'=>$response['agent'],
                        'insert_id'=>$etva_server->getId(),
                        'response'=>$msg_i18n);

        return  $result;


    }


    public function send_edit(EtvaNode $etva_node, $orig_server, $server_data)
    {
        $method = self::SERVER_EDIT;
        $etva_server = $this->etva_server;

        $etva_server->fromArray($server_data,BasePeer::TYPE_FIELDNAME);        

        /*
         * process networks
         */
        $networks_changed = isset($server_data['networks']);

        if($networks_changed)
        {

            $check_nics_available = $this->check_nics_availability($etva_node, $server_data['networks'],$method);
            if(!$check_nics_available['success']) return $check_nics_available;

        }else
        {
            /*
             * clone networks and add to server copy
             */
            $networks = $orig_server->getEtvaNetworks();
            foreach($networks as $network) $etva_server->addEtvaNetwork($network);

        }


        $disks_changed = isset($server_data['disks']);
        $disks = $server_data['disks'];

        // if disks data was changed and there is no disks to associate with server return error.
        if($disks_changed && empty($disks)){

            $error_decoded = $response['error'];

            $result = array('success'=>false,'agent'=>'ETVA','error'=>'No disks found');

            return  $result;

        }


        if($disks_changed)
        {
            /*
             * create disks objects and add to server copy
             */
            $check_disks_available = $this->check_disks_availability($etva_node, $server_data['disks'], $method);
            //error processing parameters
            if(!$check_disks_available['success']) return $check_disks_available;
        }
        else
        {
            /*
             * clone disks and add to server copy
             */
            $disks = $orig_server->getEtvaServerLogicals();
            foreach($disks as $disk) $etva_server->addEtvaServerLogical($disk);

        }

        /*
         * check mem available
         */
        $mem_available = $etva_node->getMemfree();
        $mult = 1;
        if($server_data['mem']<$orig_server->getMem()) $mult = -1;

        $server_mem_diff = abs($server_data['mem'] - $orig_server->getMem());
        
        $server_mem = Etva::MB_to_Byteconvert($server_mem_diff);
        if($mem_available < ($server_mem_diff*$mult)){
            $msg = Etva::getLogMessage(array('name' => $etva_node->getName(), 'info' => $server_mem ), EtvaNodePeer::_ERR_MEM_AVAILABLE_);
            $msg_i18n = sfContext::getInstance()->getI18N()->__(EtvaNodePeer::_ERR_MEM_AVAILABLE_,array('%name%' => $etva_node->getName(), '%info%' => $server_mem));

            return array('success'=>false,'agent'=>$etva_node->getName(),'info'=>$msg_i18n,'error'=>$msg_i18n);
        }


        $this->buildServer($method);

        $params = $etva_server->_VA();

        $response = $etva_node->soapSend($method,$params);
        $result = $this->processEditResponse($etva_node,$orig_server,$response);
        return $result;
    }

    public function processEditResponse($etva_node,$orig_server,$response)
    {
        $etva_server = $this->etva_server;
        $server_name = $etva_server->getName();

        if(!$response['success']){

            $error_decoded = $response['error'];

            $result = $response;

            $msg_i18n = sfContext::getInstance()->getI18N()->__(EtvaServerPeer::_ERR_EDIT_,array('%name%'=>$server_name,'%info%'=>$error_decoded));
            $result['error'] = $msg_i18n;

            //notify event log
            $message = Etva::getLogMessage(array('name'=>$server_name,'info'=>$response['info']), EtvaServerPeer::_ERR_EDIT_);
            sfContext::getInstance()->getEventDispatcher()->notify(
                new sfEvent($response['agent'], 'event.log',
                    array('message' => $message,'priority'=>EtvaEventLogger::ERR)));

            return $result;

        }

        /*
         * if all went ok make changes to DB....
         */

        /* remove server networks if networks have changed */
        if($this->collNetworks) $orig_server->deleteNetworks();

        // if disk data has been changed removed old disks references and keep lvs,
        if($this->collDisks) $orig_server->deleteDisks(true);                

        // get some info from response...
        $response_decoded = (array) $response['response'];
        $returned_status = $response_decoded['_okmsg_'];
        $returned_object = (array) $response_decoded['_obj_'];

        //update some server data from agent response
        $etva_server->initData($returned_object);


        //update node mem available
        $mult = 1;
        $cur_avail = $etva_node->getMemfree();        
        if($etva_server->getMem()<$orig_server->getMem()) $mult = -1;

        $server_mem_diffMB = abs($etva_server->getMem() - $orig_server->getMem());
        $server_mem_diff = Etva::MB_to_Byteconvert($server_mem_diffMB);
        $cur_free = $cur_avail - ($server_mem_diff*$mult);

        $etva_server->setNew(false);        
        $etva_server->save();
        $etva_node->setMemfree($cur_free);
        $etva_node->save();

        $message = Etva::getLogMessage(array('name'=>$server_name), EtvaServerPeer::_OK_EDIT_);
        $msg_i18n = sfContext::getInstance()->getI18N()->__(EtvaServerPeer::_OK_EDIT_,array('%name%'=>$server_name));

        sfContext::getInstance()->getEventDispatcher()->notify(new sfEvent($etva_node->getName(), 'event.log', array('message' => $message)));

        $result = $response;
        $result['response'] = $msg_i18n;
        return  $result;


    }



    public function buildServer($method)
    {
        $etva_server = $this->etva_server;

        if($method==self::SERVER_CREATE)
        {

            $etva_server->setUuid(EtvaServerPeer::generateUUID());

            $vnc_keymap = EtvaSettingPeer::retrieveByPk('vnc_keymap');
            $etva_server->setVncKeymap($vnc_keymap->getValue());

            $user_groups = sfContext::getInstance()->getUser()->getGroups();
            $server_sfgroup = array_shift($user_groups);

            //if user has group then put one of them otherwise put DEFAULT GROUP ID
            if($server_sfgroup) $etva_server->setsfGuardGroup($server_sfgroup);
            else $etva_server->setsfGuardGroup(sfGuardGroupPeer::getDefaultGroup());

        }


        foreach($this->collNetworks as $coll) $etva_server->addEtvaNetwork($coll);

        foreach($this->collDisks as $coll) $etva_server->addEtvaServerLogical($coll);        

    }


    public function check_disks_availability($etva_node, $disks, $method)
    {

        $etva_server = $this->etva_server;

        $collDisks = array();
        $i = 0;
        foreach($disks as $disk){

            if(!$etva_lv = EtvaLogicalvolumePeer::retrieveByPK($disk['id'])){

                $msg = Etva::getLogMessage(array('id'=>$disk['id']), EtvaLogicalvolumePeer::_ERR_NOTFOUND_ID_);
                $msg_i18n = sfContext::getInstance()->getI18N()->__(EtvaLogicalvolumePeer::_ERR_NOTFOUND_ID_,array('%id%'=>$disk['id']));

                $error = array('success'=>false,'agent'=>$etva_node->getName(),'error'=>$msg_i18n);

                //notify event log
                $message = Etva::getLogMessage(array('name'=>$etva_server->getName(),'info'=>$msg), EtvaServerPeer::_ERR_CREATE_);
                sfContext::getInstance()->getEventDispatcher()->notify(
                    new sfEvent($error['agent'], 'event.log',
                        array('message' => $message,'priority'=>EtvaEventLogger::ERR)));

                return $error;
            }
            
            $lv = $etva_lv->getLv();

            /*
             * check if is not system lv
             */
            if($etva_lv->getMounted()){

                $msg = Etva::getLogMessage(array('name'=>$lv), EtvaLogicalvolumePeer::_ERR_SYSTEM_LV_);
                $msg_i18n = sfContext::getInstance()->getI18N()->__(EtvaLogicalvolumePeer::_ERR_SYSTEM_LV_,array('%name%'=>$lv));

                $error = array('success'=>false,'agent'=>$etva_node->getName(),'error'=>$msg_i18n,'info'=>$msg_i18n);

                //notify system log
                $message = Etva::getLogMessage(array('name'=>$etva_server->getName(),'info'=>$msg), EtvaServerPeer::_ERR_CREATE_);
                sfContext::getInstance()->getEventDispatcher()->notify(new sfEvent($etva_node->getName(), 'event.log',array('message' => $message,'priority'=>EtvaEventLogger::ERR)));

                return $error;
            }

            //check if lv already marked as 'in use'
            if($method==self::SERVER_CREATE && $etva_lv->getInUse()){
                $lv_server = $etva_lv->getEtvaServer();
                $msg = Etva::getLogMessage(array('name'=>$lv,'server'=>$lv_server->getName()), EtvaLogicalvolumePeer::_ERR_INUSE_);
                $msg_i18n = sfContext::getInstance()->getI18N()->__(EtvaLogicalvolumePeer::_ERR_INUSE_,array('%name%'=>$lv,'%server%'=>$lv_server->getName()));

                $error = array('success'=>false,'agent'=>$etva_node->getName(),'error'=>$msg_i18n,'info'=>$msg_i18n);

                //notify event log
                $message = Etva::getLogMessage(array('name'=>$etva_server->getName(),'info'=>$msg), EtvaServerPeer::_ERR_CREATE_);
                sfContext::getInstance()->getEventDispatcher()->notify(
                    new sfEvent($error['agent'], 'event.log',
                        array('message' => $message,'priority'=>EtvaEventLogger::ERR)));

                return $error;
            }
            
            $etva_sl = new EtvaServerLogical();
            $etva_sl->setLogicalvolumeId($etva_lv->getId());
            $etva_sl->setDiskType($disk['disk_type']);
            $etva_sl->setBootDisk($i);
            $i++;
            $collDisks[] = $etva_sl;


        }// end each disk

        $this->collDisks = $collDisks;

        return array('success'=>true);


    }

    public function check_nics_availability($etva_node, $networks, $method)
    {
        $etva_server = $this->etva_server;

        $collNetworks = array();

        // check if networks are available
        foreach ($networks as $network){

            $etva_vlan = EtvaVlanPeer::retrieveByPk($network['vlan_id']);
            $etva_mac = EtvaMacPeer::retrieveByMac($network['mac']);


            if(!$etva_mac || !$etva_vlan){

                $msg = Etva::getLogMessage(array(), EtvaNetworkPeer::_ERR_);
                $msg_i18n = sfContext::getInstance()->getI18N()->__(EtvaNetworkPeer::_ERR_,array());

                $error = array('success'=>false,'agent'=>$etva_node->getName(),'error'=>$msg_i18n);

                //notify event log
                $message = Etva::getLogMessage(array('name'=>$etva_server->getName(),'info'=>$msg), EtvaServerPeer::_ERR_CREATE_);
                sfContext::getInstance()->getEventDispatcher()->notify(
                    new sfEvent($error['agent'], 'event.log',
                        array('message' => $message,'priority'=>EtvaEventLogger::ERR)));

                return $error;

            }

            if($method==self::SERVER_CREATE && $etva_mac->getInUse()){

                $msg = Etva::getLogMessage(array('name'=>$etva_mac->getMac()), EtvaMacPeer::_ERR_ASSIGNED_);
                $msg_i18n = sfContext::getInstance()->getI18N()->__(EtvaMacPeer::_ERR_ASSIGNED_,array('%name%'=>$etva_mac->getMac()));

                $error = array('success'=>false,'agent'=>$etva_node->getName(),'info'=>$msg_i18n,'error'=>$msg_i18n);

                //notify event log
                $message = Etva::getLogMessage(array('name'=>$etva_server->getName(),'info'=>$msg), EtvaServerPeer::_ERR_CREATE_);
                sfContext::getInstance()->getEventDispatcher()->notify(
                    new sfEvent($error['agent'], 'event.log',
                        array('message' => $message,'priority'=>EtvaEventLogger::ERR)));

                return $error;

            }

            $etva_network = new EtvaNetwork();
            $etva_network->fromArray($network,BasePeer::TYPE_FIELDNAME);
            $collNetworks[] = $etva_network;
        }
        $this->collNetworks = $collNetworks;

        return array('success'=>true);

    }


    /*
     * send migrate
     */
    public function send_migrate(EtvaNode $from_etva_node, EtvaNode $to_etva_node)
    {
        $method = self::SERVER_MIGRATE;

        $params = array(
                    'daddr'=>$to_etva_node->getIp(),
                    'uuid'=>$this->etva_server->getUuid());

        $preCond = $this->preSend($method, $from_etva_node, $to_etva_node);

        if(!$preCond['success']) return $preCond;

        $response = $from_etva_node->soapSend($method,$params);
        $result = $this->processMigrateResponse($from_etva_node, $to_etva_node, $response, $method);
        return $result;
    }

    /*
     * send move
     */
    public function send_move(EtvaNode $from_etva_node, EtvaNode $to_etva_node)
    {
        $method = self::SERVER_MOVE;

        $preCond = $this->preSend($method, $from_etva_node, $to_etva_node);

        if(!$preCond['success']) return $preCond;

        $params = $this->etva_server->_VA();


        $response = $to_etva_node->soapSend(self::SERVER_CREATE,$params);
        $result = $this->processMigrateResponse($from_etva_node, $to_etva_node, $response, $method);

        if($result['success'])
        {
            // remove vm from source

            $params = array('uuid'=>$this->etva_server->getUuid(),'keep_fs' =>1);
            $response = $from_etva_node->soapSend(self::SERVER_REMOVE,$params);
            $response_rm = $this->processRemoveResponse($from_etva_node,$response,$method,$params['keep_fs']);

            if(!$response_rm['success']){

                
                $error_decoded = $response_rm['info'];

                $msg_i18n = sfContext::getInstance()->getI18N()->__(EtvaServerPeer::_ERR_MOVE_,array('%name%'=>$this->etva_server->getName(),
                                    '%from%'=>$from_etva_node->getName(),'%to%'=>$to_etva_node->getName(),'%info%'=>$error_decoded));
                $response_rm['error'] = $msg_i18n;

                $message = Etva::getLogMessage(array('name'=>$this->etva_server->getName(),
                            'from'=>$from_etva_node->getName(),
                            'to'=>$to_etva_node->getName(),
                            'info'=>$response_rm['info']), EtvaServerPeer::_ERR_MOVE_);

                sfContext::getInstance()->getEventDispatcher()->notify(new sfEvent($response['agent'], 'event.log',array('message' => $message,'priority'=>EtvaEventLogger::ERR)));

            }
            else return $result;

        }

        return $result;
    }

    public function preSend($method,$from_etva_node,$to_etva_node)
    {
        $etva_server = $this->etva_server;

        switch($method){
            case self::SERVER_MIGRATE :
                                    $msg_ok_type = EtvaLogicalvolumePeer::_OK_REMOVE_;
                                    $msg_err_type = EtvaLogicalvolumePeer::_ERR_REMOVE_;
                                    $err_cond = EtvaServerPeer::_ERR_MIGRATE_FROMTO_COND_;
                                    $err_op = EtvaServerPeer::_ERR_MIGRATE_FROMTO_;
                                    $err = EtvaServerPeer::_ERR_MIGRATE_;
                                    break;
            case self::SERVER_MOVE :
                                    $msg_ok_type = EtvaLogicalvolumePeer::_OK_REMOVE_;
                                    $msg_err_type = EtvaLogicalvolumePeer::_ERR_REMOVE_;
                                    $err_cond = EtvaServerPeer::_ERR_MOVE_FROMTO_COND_;
                                    $err_op = EtvaServerPeer::_ERR_MOVE_FROMTO_;
                                    $err = EtvaServerPeer::_ERR_MOVE_;
                                    break;
        }

        if(($from_etva_node->getId() == $to_etva_node->getId()) || ($from_etva_node->getClusterId() != $to_etva_node->getClusterId()) ){

            $msg = Etva::getLogMessage(array('name'=>$etva_server->getName()), $err_cond);
            $msg_i18n = sfContext::getInstance()->getI18N()->__($err_cond,array('%name%'=>$etva_server->getName()));

            $error = array('success'=>false,'agent'=>'ETVA','error'=>$msg_i18n,'info'=>$msg_i18n);

            //notify event log
            $message = Etva::getLogMessage(array('name'=>$etva_server->getName(),'info'=>$msg), $err );
            sfContext::getInstance()->getEventDispatcher()->notify(
                new sfEvent('ETVA', 'event.log',
                    array('message' => $message,'priority'=>EtvaEventLogger::ERR)));

            return $error;


        }


        /*
         * check server memory
         */
        $to_mem_available = $to_etva_node->getMemfree();
        $server_mem = $etva_server->getMem();
        $server_memBytes = Etva::MB_to_Byteconvert($server_mem);
        if($to_mem_available < $server_memBytes){

            $no_mem_msg = Etva::getLogMessage(array('name' => $to_etva_node->getName(), 'info' => $server_mem ), EtvaNodePeer::_ERR_MEM_AVAILABLE_);
            $no_mem_msg_i18n = sfContext::getInstance()->getI18N()->__(EtvaNodePeer::_ERR_MEM_AVAILABLE_,array('%name%'=>$to_etva_node->getName(),
                                    '%info%'=>$server_mem));


            $msg_i18n = sfContext::getInstance()->getI18N()->__($err_op,array('%name%'=>$etva_server->getName(),
                                    '%from%'=>$from_etva_node->getName(),'%to%'=>$to_etva_node->getName(),'%info%'=>$no_mem_msg_i18n));

            $error = array('success'=>false,'agent'=>'ETVA','error'=>$msg_i18n,'info'=>$msg_i18n);

            $message = Etva::getLogMessage(array('name'=>$etva_server->getName(),
                            'from'=>$from_etva_node->getName(),
                            'to'=>$to_etva_node->getName(),
                            'info'=>$no_mem_msg), $err);

            sfContext::getInstance()->getEventDispatcher()->notify(
                new sfEvent('ETVA', 'event.log',
                    array('message' => $message,'priority'=>EtvaEventLogger::ERR)));

            return $error;
        }
        

        $disks_shared = $etva_server->isAllSharedLogicalvolumes();
        if(!$disks_shared)
        {

            $msg_i18n = sfContext::getInstance()->getI18N()->__($err_op,array('%name%'=>$etva_server->getName(),
                                    '%from%'=>$from_etva_node->getName(),'%to%'=>$to_etva_node->getName(),'%info%'=>EtvaLogicalvolumePeer::_NOTALLSHARED_));

            $error = array('success'=>false,'agent'=>'ETVA','error'=>$msg_i18n,'info'=>$msg_i18n);

            $message = Etva::getLogMessage(array('name'=>$etva_server->getName(),
                            'from'=>$from_etva_node->getName(),
                            'to'=>$to_etva_node->getName(),
                            'info'=>EtvaLogicalvolumePeer::_NOTALLSHARED_), $err);

            sfContext::getInstance()->getEventDispatcher()->notify(
                new sfEvent('ETVA', 'event.log',
                    array('message' => $message,'priority'=>EtvaEventLogger::ERR)));

            return $error;

        }

        return array('success'=>true);


    }







    /*
     * process response
     */
    public function processMigrateResponse($from_etva_node, $to_etva_node, $response, $method)
    {
        $etva_server = $this->etva_server;


        switch($method){
            case self::SERVER_MIGRATE :
                                    $msg_ok_type = EtvaServerPeer::_OK_MIGRATE_FROMTO_;
                                    $err_op = EtvaServerPeer::_ERR_MIGRATE_FROMTO_;
                                    break;
            case self::SERVER_MOVE :
                                    $msg_ok_type = EtvaServerPeer::_OK_MOVE_FROMTO_;
                                    $err_op = EtvaServerPeer::_ERR_MOVE_FROMTO_;
                                    break;
        }

        if(!$response['success']){

            $error_decoded = $response['error'];

            $result = $response;

            $msg_i18n = sfContext::getInstance()->getI18N()->__($err_op,array('%name%'=>$etva_server->getName(),
                                    '%from%'=>$from_etva_node->getName(),'%to%'=>$to_etva_node->getName(),'%info%'=>$error_decoded));
            $result['error'] = $msg_i18n;

            $message = Etva::getLogMessage(array('name'=>$etva_server->getName(),
                            'from'=>$from_etva_node->getName(),
                            'to'=>$to_etva_node->getName(),
                            'info'=>$response['info']), $err_op);

            sfContext::getInstance()->getEventDispatcher()->notify(new sfEvent($response['agent'], 'event.log',array('message' => $message,'priority'=>EtvaEventLogger::ERR)));


            return  $result;

        }

        $update_node_server = $this->reloadVm($to_etva_node);

        if($update_node_server['success'])
        {

            $msg_i18n = sfContext::getInstance()->getI18N()->__($msg_ok_type,array('%name%'=>$etva_server->getName(),
                                    '%from%'=>$from_etva_node->getName(),'%to%'=>$to_etva_node->getName(),'%info%'=>$returned_status));

            $result = array('success'=>true,'agent'=>$response['agent'],'response'=>$msg_i18n);

            //notify event log
            $message = Etva::getLogMessage(array('name'=>$etva_server->getName(),
                            'from'=>$from_etva_node->getName(),
                            'to'=>$to_etva_node->getName(),
                            'info'=>$response['info']), $msg_ok_type);

            sfContext::getInstance()->getEventDispatcher()->notify(new sfEvent($response['agent'], 'event.log',array('message' => $message)));
        }

        return $update_node_server;

    }


    public function reloadVm(EtvaNode $etva_node)
    {
        $etva_server = $this->etva_server;
        $method = self::SERVER_GET;
        $params = array('uuid'=>$etva_server->getUuid(),'force'=>1);

        $response = $etva_node->soapSend($method,$params);


        if(!$response['success']){

            $error_decoded = $response['error'];

            $result = $response;

            $msg_i18n = sfContext::getInstance()->getI18N()->__(EtvaServerPeer::_ERR_RELOAD_,array('%name%'=>$etva_server->getName(),
                                    '%node%'=>$etva_node->getName(),'%info%'=>$error_decoded));
            $result['error'] = $msg_i18n;

            $message = Etva::getLogMessage(array('name'=>$etva_server->getName(),
                            'node'=>$etva_node->getName(),
                            'info'=>$response['info']), EtvaServerPeer::_ERR_RELOAD_);

            sfContext::getInstance()->getEventDispatcher()->notify(new sfEvent($response['agent'], 'event.log',array('message' => $message,'priority'=>EtvaEventLogger::ERR)));

            return  $result;

        }


        $returned_object = (array) $response['response'];

        $etva_server->initData($returned_object);
        $etva_server->setEtvaNode($etva_node);

        //update agent free memory
        $cur_avail = $etva_node->getMemfree();
        $cur_free = $cur_avail - Etva::MB_to_Byteconvert($etva_server->getMem());
        $etva_node->setMemfree($cur_free);

        $etva_server->save();


        $msg_i18n = sfContext::getInstance()->getI18N()->__(EtvaServerPeer::_OK_RELOAD_,array('%name%'=>$etva_server->getName(),
                                    '%node%'=>$etva_node->getName()));

        $result = array('success'=>true,'agent'=>$response['agent'],'response'=>$msg_i18n);

        //notify event log
        $message = Etva::getLogMessage(array('name'=>$etva_server->getName(),'node'=>$etva_node->getName()), EtvaServerPeer::_OK_RELOAD_);

        sfContext::getInstance()->getEventDispatcher()->notify(new sfEvent($response['agent'], 'event.log',array('message' => $message)));

        return $result;


    }

}