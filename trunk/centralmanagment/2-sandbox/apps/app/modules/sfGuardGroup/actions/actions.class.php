<?php
require_once(sfConfig::get('sf_plugins_dir').'/sfGuardPlugin/modules/sfGuardGroup/lib/BasesfGuardGroupActions.class.php');

/**
 * sfGuardGroup actions.
 *
 * @package    sfGuardPlugin
 * @subpackage sfGuardGroup
 * @author     Fabien Potencier
 * @version    SVN: $Id: actions.class.php 12965 2008-11-13 06:02:38Z fabien $
 */
class sfGuardGroupActions extends BasesfGuardGroupActions
{
    public function executeView(sfWebRequest $request)
    {
    }    

    public function executeJsonNothing(sfWebRequest $request)
    {
        $result = array('success'=>true);
        $result = json_encode($result);

        $this->getResponse()->setHttpHeader('Content-type', 'application/json');
        return $this->renderText('{"metaData":{"totalProperty":"totalCount","root":"results","id":"id","fields":[{"name":"id","type":"int"},{"name":"firstname"},{"name":"lastname"},{"name":"username"},{"name":"email"},{"name":"active"},{"name":"updateTime"}]},"totalCount":1,"results":[{"id":160,"class":"TutorAccount","active":"Yes","createTime":new Date(1240424045000),"email":"wilt@moore.com","firstname":"Wsda","lastname":"Moore","note":"ssdf","password":"tota","updateTime":new Date(1240559517000),"username":"wilt"}]}');
    }

    /*
     *
     * list all groups
     * 
     * return json array response
     *
     */
    public function executeJsonList(sfWebRequest $request)
    {
        $c = new Criteria();
        $groups = sfGuardGroupPeer::doSelect($c);
        $elements = array();
        foreach ($groups as $group) $elements[] = $group->toArray();
      
        $return = array('data'  => $elements);

        $result=json_encode($return);
        $this->getResponse()->setHttpHeader('Content-type', 'application/json');
        return $this->renderText($result);
    }
   
    public function executeJsonCreate(sfWebRequest $request)
    {                
        $isAjax = $request->isXmlHttpRequest();

        if(!$isAjax) return $this->redirect('@homepage');

        if(!$request->isMethod('post')){
            $info = array('success'=>false,'error'=>'Wrong parameters');
            $error = $this->setJsonError($info);
            return $this->renderText($error);
        }

        $this->form = new sfGuardGroupForm();

        $result = $this->processJsonForm($request, $this->form);

        if(!$result['success']){
            $error = $this->setJsonError($result);
            return $this->renderText($error);
        }

        $result = json_encode($result);

        $this->getResponse()->setHttpHeader('Content-type', 'application/json');

        return $this->renderText($result);

    }

    public function executeJsonUpdate(sfWebRequest $request)
    {        
        $isAjax = $request->isXmlHttpRequest();

        if(!$isAjax) return $this->redirect('@homepage');

        $id = $request->getParameter('id');
        $group = sfGuardGroupPeer::retrieveByPK($id);
        if(!$group) $group_form = new sfGuardGroupForm();
        else $group_form = new sfGuardGroupForm($group);

        $result = $this->processForm($request, $group_form);

        try{
            
            // remove existing permissions for the given group
            $c = new Criteria();
            $c->add(EtvaPermissionGroupPeer::GROUP_ID, $request->getParameter('id'), Criteria::EQUAL);
            $g_p = EtvaPermissionGroupPeer::doSelect($c);   //filter group permissions

            foreach ($g_p as $p){
                $p->delete();
            }

            // add new permission set
            $perm_list = $request->getParameter('sf_guard_group_permission_list');
            $perm_list_dec = json_decode($perm_list);

            foreach ($perm_list_dec as $object){
                $g_p = new EtvaPermissionGroup();
                $g_p->setGroupId($request->getParameter('id'));
                $g_p->setEtvapermId($object);
                $g_p->save();
            }
            
        }catch(Exception $e){
            $result = array('success' => false,
                          'error'   => 'Could not perform operation',
                          'agent'   =>sfConfig::get('config_acronym'),
                          'info'    => 'Could not perform operation');
        }

        if(!$result['success']){
            $error = $this->setJsonError($result);
            return $this->renderText($error);
        }

        $msg_i18n = $this->getContext()->getI18N()->__('Group saved successfully');
        $response = array('success'=>true,
                            'agent' =>  'Central Management',
                            'response'  => $msg_i18n,
                            'group_id' => $result['object']['Id'] );
        $return = json_encode($response);
        $this->getResponse()->setHttpHeader('Content-type', 'application/json');
        return $this->renderText($return);
    }

    public function executeJsonUpdateField(sfWebRequest $request)
    {
        $isAjax = $request->isXmlHttpRequest();

        if(!$isAjax) return $this->redirect('@homepage');

        if(!$request->isMethod('post') && !$request->isMethod('put')){
            $info = array('success'=>false,'error'=>'Wrong parameters');
            $error = $this->setJsonError($info);
            return $this->renderText($error);
        }

        if(!$sfGuardGroup = sfGuardGroupPeer::retrieveByPk($request->getParameter('id')))
        {
            $msg_i18n = $this->getContext()->getI18N()->__(sfGuardGroupPeer::_ERR_NOTFOUND_ID_,array('%id%'=>$id));

            $info = array('success'=>false,'error'=>$msg_i18n);
            $error = $this->setJsonError($info);
            return $this->renderText($error);
        }

        $sfGuardGroup->setByName($request->getParameter('field'), $request->getParameter('value'));
        $sfGuardGroup->save();

        $result = array('success'=>true);
        $result = json_encode($result);
        $this->getResponse()->setHttpHeader('Content-type', 'application/json');
        return $this->renderText($result);
    }


    /*
     *
     * If GROUP is the DEFAULT GROUP will return error
     *
     */
    public function executeJsonDelete(sfWebRequest $request)
    {
        $request->checkCSRFProtection();
        
        $isAjax = $request->isXmlHttpRequest();

        if(!$isAjax) return $this->redirect('@homepage');

        if(!$sfGuardGroup = sfGuardGroupPeer::retrieveByPk($request->getParameter('id'))){
            $msg_i18n = $this->getContext()->getI18N()->__(sfGuardGroupPeer::_ERR_NOTFOUND_ID_,array('%id%'=>$id));
            $info = array('success'=>false,'error'=>$msg_i18n);
            $error = $this->setJsonError($info);
            return $this->renderText($error);
        }

        try
        {
            $sfGuardGroup->delete();
            $result = array('success'=>true);
            $result = json_encode($result);
            $this->getResponse()->setHttpHeader('Content-type', 'application/json');
            return $this->renderText($result);
		}
        catch (Exception $e){
            $emsg = $e->getMessage();
            $info = array('success'=>false,
                          'error'=>$emsg, 'info'=>$emsg);
            $error = $this->setJsonError($info);
            return $this->renderText($error);
		}           
        
    }


    //TODO: change to adjusto to new permissions... items must be pre-selected...
    public function executeJsonGridWithPerms($request)
    {
        $isAjax = $request->isXmlHttpRequest();

        if(!$isAjax) return $this->redirect('@homepage');

        $limit = $this->getRequestParameter('limit', 10);
        $page = floor($this->getRequestParameter('start', 0) / $limit)+1;

        // pager
        $this->pager = new sfPropelPager('sfGuardGroup', $limit);

        $c = new Criteria();

        $this->addSortCriteria($c);
    

        $this->pager->setCriteria($c);
        $this->pager->setPage($page);
    
        $this->pager->setPeerMethod('doSelect');
        $this->pager->setPeerCountMethod('doCount');

        $this->pager->init();


        $elements = array();
        $i = 0;

        # Get data from Pager
        foreach($this->pager->getResults() as $item){
            $elements[$i] = $item->toArray();

            // for each group, fetch its permissions
            $c = new Criteria();
            $c->add(EtvaPermissionGroupPeer::GROUP_ID, $item->getId(), Criteria::EQUAL);
            //$c->addJoin(EtvaPermissionPeer::ID, EtvaPermissionGroupPeer::ETVAPERM_ID);

            $perms = EtvaPermissionGroupPeer::doSelect($c);

            //error_log(print_r($perms, true));
            foreach ($perms as $p){
//                error_log("permissao".$p->getEtvaPermission()->getId());
                $elements[$i]['sf_guard_group_permission_list'][] = $p->getEtvaPermission()->getId();
            }

            //foreach ($item->getsfGuardGroupPermissionsJoinsfGuardPermission() as $gp)
            //{
                //$permission = $gp->getsfGuardPermission();
                //$elements[$i]['sf_guard_group_permission_list'][] = $permission->getId();
                //error_log("cicle");
            //}

//            error_log($item->getId());


            $i++;
        }

//        error_log(print_r($elements, true));

        $final = array(
                        'total' => $this->pager->getNbResults(),
                        'data'  => $elements);

        $result = json_encode($final);

        $this->getResponse()->setHttpHeader('Content-type', 'application/json');
        return $this->renderText($result);
    }

    protected function addSortCriteria($criteria)
    {
        if ($this->getRequestParameter('sort')=='') return;

        $column = sfGuardGroupPeer::translateFieldName(sfInflector::camelize($this->getRequestParameter('sort')), BasePeer::TYPE_PHPNAME, BasePeer::TYPE_COLNAME);

        if ('asc' == strtolower($this->getRequestParameter('dir')))
            $criteria->addAscendingOrderByColumn($column);
        else
            $criteria->addDescendingOrderByColumn($column);
    }


    protected function processForm(sfWebRequest $request, sfForm $form)
    {
        $fieldSc = $form->getFormFieldSchema();
        $widget = $fieldSc->getWidget();
        $params = array();

        foreach($widget->getFields() as $key => $object){
            if($key == "sf_guard_group_permission_list"){
                continue;
            }
            $data = $request->getParameter($key);
            $data_dec = json_decode($data);
            $params[$key] = is_array($data_dec) ? $data_dec : $data;
        }

        $form->bind($params);

        if($form->isValid())
        {
            try{
                $group = $form->save();
            }catch(Exception $e){
                $response = array('success' => false,
                              'error'   => 'Could not perform operation',
                              'agent'   =>sfConfig::get('config_acronym'),
                              'info'    => 'Could not perform operation');
                return $response;
            }

            return array('success'=>true, 'object'=>$group->toArray());

        }
        else
        {
            $errors = array();
            foreach ($form->getFormattedErrors() as $error) $errors[] = $error;

            $error_msg = implode($errors);
            $info = implode('<br>',$errors);
            $response = array('success' => false,
                              'error'   => $error_msg,
                              'agent'   =>sfConfig::get('config_acronym'),
                              'info'    => $info);
            return $response;
        }
    }

    protected function setJsonError($info,$statusCode = 400){

        if(isset($info['faultcode']) && $info['faultcode']=='TCP') $statusCode = 404;
        $this->getContext()->getResponse()->setStatusCode($statusCode);
        $error = json_encode($info);
        $this->getResponse()->setHttpHeader('Content-type', 'application/json');
        return $error;

    }
    
}
