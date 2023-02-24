<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Db;
use think\Log;
use app\admin\model\Goods;

use Exception;

use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\response\Json;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Goodsorder extends Backend
{

    /**
     * Goodsorder模型对象
     * @var \app\admin\model\Goodsorder
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Goodsorder;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("goodsNameList", Db::name('goods')->field('goodsid,goodsname')->select());
    }
    public function list(){
        $id=input('id');
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }
        //如果发送的来源是 Selectpage，则转发到 Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        Log::write($id);
        $customer_id=$this->request->request('customer_id');
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model
            ->where($where)
            ->where('customer_id',$customer_id)
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    public function updateStatus1(){
        $id=input('id');
        $this->model->save([
            'status'  => '1'
        ],['orderid' => $id]);
    }
    public function updateStatus2(){
        $id=input('id');
        $this->model->save([
            'status'  => '0'
        ],['orderid' => $id]);
    }
    public function updateStatus3(){
        $id=input('id');
        $this->model->save([
            'status'  => '2'
        ],['orderid' => $id]);
    }

    
        /**
     * 添加
     *
     * @return string
     * @throws \think\Exception
     */
    public function add()
    {
        if (false === $this->request->isPost()) {
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
            $params[$this->dataLimitField] = $this->auth->id;
        }
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                $this->model->validateFailException()->validate($validate);
            }
            $goodsInfos=json_decode($params['goods'],true);
            $total=0;
            foreach($goodsInfos as $infos){
                $goods=Goods::get($infos['goodsid']);
                $total+=$goods->goodsprice*$infos['quantity'];
            };
            $params=[
                               
                'customer_id'=>$params['customer_id'],
                'address_id'=>$params['address_id'],
                'name_id'=>$params['name_id'],
                'phone_id'=>$params['phone_id'],
                'status'=>$params['status'],
                'totalprice'=>$total
            ];
            $result = $this->model->allowField(true)->save($params);
            
            foreach($goodsInfos as $infos){
                $goods=Goods::get($infos['goodsid']);
                $itemsInfo=[
                    'itemsname'=>$goods->goodsname,
                    'itemsimage'=>$goods->goodsimage,
                    'itemscontent'=>$goods->goodscontent,
                    'itemsunit'=>$goods->goodsunit,
                    'itemsprice'=>$goods->goodsprice,
                    'items_idss'=>$this->model->orderid,
                    'itemsquantity'=>$infos['quantity']
                ];
                Db::name('goodsorderitems')->data($itemsInfo)->insert();
            }
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success();
    }

    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        $result = false;
        
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            $result = $row->allowField(true)->save($params);
            $goodsInfos=json_decode($params['goods'],true);
            foreach($goodsInfos as $infos){
                $goods=Goods::get($infos['goodsid']);
                $itemsInfo=[
                    'itemsname'=>$goods->goodsname,
                    'itemsimage'=>$goods->goodsimage,
                    'itemscontent'=>$goods->goodscontent,
                    'itemsunit'=>$goods->goodsunit,
                    'itemsprice'=>$goods->goodsprice,
                    'items_idss'=>$this->model->orderid,
                    'itemsquantity'=>$infos['quantity']
                ];
                Db::table('goodsorderitems')->where('itemsid',$row)->update($itemsInfo);
            }
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


}
