<?php

namespace admin;

use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;

class AdminController extends \BaseController
{
    protected $layout = 'layouts.admin.main';

    /**
     * AdminController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        View::share('stdName',$this->getStdName());
        View::share('routeParams',$this->getRouteParams());
        if($this->model){
            $this->assignModel($this->model);
        }
        View::share('config',$this->savedConfig);
    }

    public function getList(){
        $models = $this->paginateModels();

        $this->makeView(array(
            'models'  =>  $models,
            $this->getStdName().'s' =>  $models,
        ),'admin.core.list');
    }

    public function getEdit($id = null)
    {
        if($id){
            $this->model = $this->model->find($id);
        }
        $data = array(
            'id' => $id,
            'model'   =>  $this->model,
            $this->modelName=>$this->model,
        );
        $this->beforeEdit($data);

        $view = $this->getRouteParam('c').'._form';
        if(!View::exists($view)){
            $view = 'admin.core.form';
        }
        $this->makeView($data,$view);
    }

    protected function config(){
        $config = 'crud/'.$this->getStdName();
        if(!file_exists(app_path('config/').$config.'.php')){
            $config = 'crud/admin';
        }
        return Config::get($config);
    }

    protected function assignModel($model)
    {
        $this->model = $model;

        $config = $this->config();
        $defaultConfig = Config::get('crud/admin');
        $relations = array();
        /* 将默认参数传递给module config */
        foreach($config['fields']   as $field => &$fieldConfig){
            if(isset($fieldConfig['value'])){
                $this->model->$field = $fieldConfig['value'];
            }
            $fieldConfig['form'] = array_merge(
                $defaultConfig['fields']['field_name']['form'],
                isset($fieldConfig['form']) ? $fieldConfig['form'] : array()
            );
            $fieldConfig['list'] = array_merge(
                $defaultConfig['fields']['field_name']['list'],
                isset($fieldConfig['list']) ? $fieldConfig['list'] : array()
            );
            if(isset($fieldConfig['relation'])){
                $relations[$field] = $fieldConfig['relation'];
            }
        }

        $config['list_options'] = array_merge(
            $defaultConfig['list_options'],
            isset($config['list_options']) ? $config['list_options'] : array()
        );

        $config['form_options'] = array_merge(
            $defaultConfig['form_options'],
            isset($config['form_options']) ? $config['form_options'] : array()
        );

        /* 将字段的relation汇聚出来，是为了后面的代码方便，同时减少循环 */
        $config['relations'] = $relations;

        $this->savedConfig = $config;
    }

    protected function paginateModels()
    {
        $models = array();
        if($this->model){
            $query = $this->model->newQuery();
            $this->handleListQuery($query);
            $selects = array($this->model->getTable().'.*');

            foreach($this->savedConfig['relations'] as $field=>&$params){
                $query->join($params['table'],$params['table'].'.'.$params['foreign_key'],'=',$this->model->getTable().'.'.$field);
                if(!isset($params['as'])){
                    $params['as'] = $params['table'].'_'.$params['show'];
                }
                $selects[] = $params['table'].'.'.$params['show'] . ' as '.$params['as'];
            }
            $query->select($selects);
            $orderBy = Input::get('list_order_by');
            if( $orderBy){
                $query->orderBy($this->model->getTable().'.'.$orderBy,Input::get('list_sort_asc') ? 'asc' : 'desc');
            }
            $page = Input::has('_page') ? Input::get('_page') : 10;
            $models = $query->paginate($page);
        }
        return $models;
    }


    public function postEdit($primaryKeyValue = null){
//        $resp = Redirect::action(get_class($this).'@getEdit', array('id'=>Input::get('id')))->withInput();
//        $relations  = array();

        $primaryKeyName = $this->model->getKeyName();
        if($primaryKeyValue == null){
            $primaryKeyValue = Input::get($primaryKeyName);
        }

        $fields = $this->savedConfig['fields'];
        $datas = array();
        foreach($fields as $field => $fieldConfig){
            $datas[$field] = Input::get($field);
        }

        if($primaryKeyValue){
            $this->model->where($primaryKeyName,$primaryKeyValue)->update($datas);
        }else{
            $this->model->fill($datas);
            $this->model->save();
        }
        $this->afterSave($this->model);
        $resp = Redirect::action(get_class($this).'@getList')->withMessage('save success!');

        return $resp;
    }

    public function getIndex()
    {
        $this->makeView(null,'admin.index');
    }

    public function getDelete($id){
        $this->beforeDelete($id);
        $this->model->where('id',$id)->delete();
        return Redirect::action(get_class($this).'@getList');
    }

    public function missingMethod($parameters = array())
    {
        //
        $this->makeView(null,'site.404');
    }

    protected function handleListQuery(&$query)
    {
        $searchFields = array_intersect_key($this->savedConfig['fields'],Input::all());
        foreach($searchFields as $field=> $fieldConfig){
            if(isset($fieldConfig['list']['search'])){
                $value = Input::get($field);
                $option = $fieldConfig['list']['search'];
                if($value !== ''){
                    if(isset($option['operator'])){
                        if($option['operator'] === 'like'){
                            $value = '%'.$value.'%';
                        }
                        $query = $query->where($this->model->getTable().'.'.$field,$option['operator'],$value);
                    }else{
                        $query = $query->where($this->model->getTable().'.'.$field,$value);
                    }
                }
            }
        }

    }

    protected function beforeDelete($id)
    {

    }

    protected function beforeEdit(&$data)
    {
    }

    protected function afterSave($model)
    {

    }
}
