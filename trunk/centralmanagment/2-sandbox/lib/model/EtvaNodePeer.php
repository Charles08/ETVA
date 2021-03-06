<?php

class EtvaNodePeer extends BaseEtvaNodePeer
{
    const _ERR_NOTFOUND_UUID_   = 'Node with UUID %name% could not be found';
    const _ERR_NOTFOUND_ID_   = 'Node with ID %id% could not be found';

    const _ERR_SOAPUPDATE_   = 'Node %name% could not be updated. %info%';
    const _OK_SOAPUPDATE_   = 'Node %name% updated';
    
    const _ERR_UPDATE_   = 'Node %name% could not be updated. %info%';
    const _OK_UPDATE_   = 'Node %name% updated successfully';

    const _ERR_SOAPSTATE_   = 'Node %name% state could not be checked. %info%';
    const _OK_SOAPSTATE_   = 'Node %name% state has been checked';

    const _ERR_NOPV_ = 'Node %name% does not have physical volume %pv%';
    const _ERR_NODEV_ = 'Node %name% does not have device %dev%';

    const _ERR_SOAPINIT_   = 'Node %name% could not be initialized. %info%';
    const _OK_SOAPINIT_   = 'Node %name% with UUID %uuid% and keepalive updates of %keepalive_update% (secs) initialized successfully';
    const _OK_SOAPREGISTER_   = 'Node %name% with UUID %uuid% and keepalive updates of %keepalive_update% (secs) registered successfully';

    const _OK_CHANGENAME_ = 'Node %name_old% changed successfully to %name_new%';
    const _ERR_CHANGENAME_ = 'Name of node %name% could not be changed. %info%';

    const _OK_CHANGEIP_ = 'Node %name% IP settings changed successfully';
    const _ERR_CHANGEIP_ = 'Could not change node %name% IP settings. %info%';

    const _OK_INITIALIZE_ = 'Node %name% initialized';
    const _ERR_INITIALIZE_ = 'Node %name% not initialized. %info%';
    const _INITIALIZE_PENDING_   = 'Node %name% initialization pending';
    
    const _ERR_INITIALIZE_CMD_   = 'Could not sent node %name% initialization command %cmd%. %info%';

    const _ERR_ISODIR_UMOUNT_ = 'Node %name% ISO REPO could not be umounted. %info%';

    const _STATE_UP_ = 'Node %name% is up';
    const _STATE_DOWN_ = 'Node %name% is down';

    const _PROBLEM_ = 'Some errors occurred. See events log!';

    const _ERR_MEM_AVAILABLE_   = 'Node %name% has no memory available. Need %info% MB';
    const _ERR_LIST_DEVICES_   = 'Can not list %name% devices. Verify if the Virtual Agent is running.';
    const _ERR_DEVICE_ATTACHED_   = 'The device %dev% is already attached to another virtual server. Please dettach the device before proceed.';

    const _ERR_PUTMAINTENANCE_ = 'Could not put the node %name% in maintenance mode. %info%';
    const _OK_PUTMAINTENANCE_ = 'Node %name% marked in maintenance mode successfully';

    const _ERR_SYSTEMCHECK_   = 'Node %name% could not execute system check. %info%';
    const _OK_SYSTEMCHECK_   = 'Node %name% execute system check successfully.';

    const _ERR_CHECK_MDSTAT_ = 'Node %name% receive some errors of RAID system. %info%';
    const _OK_CHECK_MDSTAT_ = 'Node %name% RAID system check ok.';

    static function getWithServers()
    {
        /*$criteria = new Criteria();
        $criteria->addJoin(self::ID, EtvaServerPeer::NODE_ID, Criteria::LEFT_JOIN);
        //$criteria->addJoin(EtvaServerPeer::SF_GUARD_GROUP_ID, sfContext::getInstance()->getUser()->getGroupsId(), Criteria::IN);
        $criteria->setDistinct();
        return self::doSelect($criteria);*/
        $list = EtvaNodeQuery::create()
                        ->useEtvaServerAssignQuery('ServerAssign',Criteria::LEFT_JOIN)
                        ->endUse()
                        ->distinct()
                        ->find();
        return $list;
    }


    public static function retrieveByIp($host)
  {
    $c = new Criteria();
    $c->add(self::IP, $host);

      return self::doSelectOne($c);
  }

  public static function generateUUID(){

      $uuid = UUID::generate(UUID::UUID_RANDOM, UUID::FMT_STRING);
      while(!self::uniqueUUID($uuid)){
          $uuid = UUID::generate(UUID::UUID_RANDOM, UUID::FMT_STRING);
      }
      return $uuid;
  }

  public static function uniqueUUID($uuid)
  {
     $c = new Criteria();
     $c->add(self::UUID, $uuid);

     $result = self::doSelectOne($c);
     if($result) return false;
     else return true;
      
  }

  /**
    * Used for compatibility of the previous version, where requests only contains the "nid". Needed since the implementation of clusters.
    */
  public static function getOrElectNode(sfWebRequest $request)
  {

     // get parameters
     $nid = $request->getParameter('nid');
     $cid = $request->getParameter('cid');
     $level = $request->getParameter('level');
     $vg = $request->getParameter('vg');
     $dev = $request->getParameter('dev');
     $lv = $request->getParameter('lv');

     return self::getOrElectNodeFromArray( array( 'node'=>$nid, 'cluster'=>$cid, 'level'=>$level,
                                        'volumegroup'=>$vg, 'device'=>$dev, 'logicalvolume'=>$logicalvolume ) );
  }
  public static function getOrElectNodeFromArray($arguments)
  {

     // get parameters
     $nid = (isset($arguments['node'])) ? $arguments['node'] : null;
     $cid = (isset($arguments['cluster'])) ? $arguments['cluster'] : null;
     $level = (isset($arguments['level'])) ? $arguments['level'] : null;
     $vg = (isset($arguments['volumegroup'])) ? $arguments['volumegroup'] : null;
     $dev = (isset($arguments['device'])) ? $arguments['device'] : null;
     $lv = (isset($arguments['logicalvolume'])) ? $arguments['logicalvolume'] : null;

     // check level - back compatibility
     if(!$level)
         $level = 'node';

     if($level == 'cluster'){
         $etva_cluster = EtvaClusterPeer::retrieveByPK($cid);
        return EtvaNode::getFirstActiveNode($etva_cluster);
     }elseif($level == 'node'){
        $etva_node = EtvaNodePeer::retrieveByPK($nid); 

        if($lv){
            $c = new Criteria();
            $c->add(EtvaLogicalvolumePeer::STORAGE_TYPE, EtvaLogicalvolume::STORAGE_TYPE_LOCAL_MAP, Criteria::ALT_NOT_EQUAL);
            $etva_lv = EtvaLogicalvolumePeer::retrieveByLv($lv, $c);
            return ($etva_lv) ? EtvaNodePeer::ElectNode($etva_node): $etva_node;
        }elseif($dev){
            $c = new Criteria();
            $c->add(EtvaPhysicalvolumePeer::STORAGE_TYPE, EtvaPhysicalvolume::STORAGE_TYPE_LOCAL_MAP, Criteria::ALT_NOT_EQUAL);
            $etva_pv = EtvaPhysicalvolumePeer::retrieveByDevice($dev, $c);
            return ($etva_pv) ? EtvaNodePeer::ElectNode($etva_node): $etva_node;            
        }elseif($vg){
             $c = new Criteria();
             $c->add(EtvaVolumeGroupPeer::STORAGE_TYPE, EtvaVolumeGroup::STORAGE_TYPE_LOCAL_MAP, Criteria::ALT_NOT_EQUAL);
             $etva_vg = EtvaVolumegroupPeer::retrieveByVg($vg, $c);
             return ($etva_vg) ? EtvaNodePeer::ElectNode($etva_node): $etva_node;                     
        }else{
             return $etva_node;
        }
     }
  }
  private static function ElectNode(EtvaNode $etva_node){
        $cluster_id = $etva_node->getClusterId();
        $etva_cluster = EtvaClusterPeer::retrieveByPK($cluster_id);
        return EtvaNode::getFirstActiveNode($etva_cluster);
  }

    public static function getDevicesInUse($etva_node, $ignore_server){
        $id = $etva_node->getId();
        $servers = EtvaServerQuery::create()
                    ->useEtvaServerAssignQuery('ServerAssign','RIGHT JOIN')
                        ->filterByNodeId($id)
                    ->endUse()
                    ->find();
            
        $devs_in_use = array();

        // gather devices
        foreach($servers as $server){
            if(isset($ignore_server) && $server->getId() == $ignore_server->getId())
                continue;

            $srv_devices = json_decode($server->getDevices());
            foreach($srv_devices as $d){
                $devs_in_use[] = $d->idvendor.$d->idproduct.$d->type;
            }
        }
        return array_unique($devs_in_use);
    }
}
