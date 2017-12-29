<?php

/**
 * 仓库货位
 *
 */
class ImportLocationModel extends BaseImportExcelModel
{

    protected $trueTableName = 'tb_wms_location_sku';
    private $_warehouseInfo;

    protected $_auto = [
//        ['CREATE_TIME', 'getTime', Model::MODEL_INSERT, 'callback'],
//        ['UPDATE_TIME', 'getTime', Model::MODEL_BOTH, 'callback'],
//        ['CREATE_USER_ID', 'getName', Model::MODEL_INSERT, 'callback'],
//        ['UPDATE_USER_ID', 'getName', Model::MODEL_BOTH, 'callback'],
//        ['CON_STAT', '1', Model::MODEL_INSERT],
//        ['CRM_CON_TYPE', 1, Model::MODEL_INSERT],
    ];

    public function fieldMapping()
    {
        return [
            'warehouse_name' => ['field_name' => L('仓库名称'), 'required' => true],
            'warehouse_code' => ['field_name' => L('仓库CODE值'), 'required' => true],
            'sku' => ['field_name' => L('SKU编码'), 'required' => true],
            'location_code' => ['field_name' => L('货位编码'), 'required' => true],
            'location_code_back' => ['field_name' => L('备用货位编码'), 'required' => true],
        ];
    }

    /**
     * 校验是否不能为空
     * @param string $row_index 行坐标
     * @param string $column_index 列坐标
     * @param $value 值
     * @return
     */
    public function valid($row_index, $column_index, $value)
    {
        //$db_field = $this->title [$column_index]['db_field'];//重写该方法的时候，必须保留这一句
        // 必填验证
        if ($this->title [$column_index]['required'] and empty($value))
            $this->record($row_index, $this->title [$column_index]['en_name'] . '('.L('必填').')');
    }

    /**
     * 代码层数据过滤
     *
     *
     */
    public function filterCodeData()
    {
        $this->_warehouseInfo = [];
        $warehouseColumnIndex = '';
        $skuColumnIndex = '';
        $locationColumnIndex = '';
        $locationBackColumnIndex = '';
        foreach ($this->title as $k => $v) {
            switch ($v ['db_field']) {
                case 'warehouse_code':
                    $warehouseColumnIndex = $k;
                    break;
                case 'sku':
                    $skuColumnIndex = $k;
                    break;
                case 'location_code':
                    $locationColumnIndex = $k;
                    break;
                case 'location_code_back':
                    $locationBackColumnIndex = $k;
                    break;
            }
        }
        // 通过仓库组装各个仓库相关的数据
        foreach ($this->data as $rowIndex => $value) {
            $this->_warehouseInfo [$value [$warehouseColumnIndex]['value']]['sku'][$rowIndex] = $value [$skuColumnIndex]['value'];
            if ($value [$locationColumnIndex]['value'])
                $this->_warehouseInfo [$value [$warehouseColumnIndex]['value']]['location_code'][$rowIndex] = $value [$locationColumnIndex]['value'];
            if ($value [$locationBackColumnIndex]['value'])
                $this->_warehouseInfo [$value [$warehouseColumnIndex]['value']]['location_code_back'][$rowIndex] = $value [$locationBackColumnIndex]['value'];
            $this->_warehouseInfo [$value [$warehouseColumnIndex]['value']]['rows'][] = $rowIndex;
        }
        $needUinqueKeys = [
            'sku',
            'location_code'
        ];
        $uniqueError = [];
        // SKU、货位编码、唯一性验证。备用货位与货位编码唯一性验证
        foreach ($this->_warehouseInfo as $warehouseCode => $info) {
            if ($exi = array_intersect($info ['location_code_back'], $info ['location_code'])) {
                $uniqueError [$warehouseCode]['location_code_back'] = $exi;
            }
            foreach ($info as $columnKey => $columnValue) {
                if (in_array($columnKey, $needUinqueKeys)) {
                    $uniqueValue = array_unique($columnValue);
                    if (count($columnValue) != count($uniqueValue)) {
                        $ret = array_diff_assoc($columnValue, $uniqueValue);
                        if ($ret)
                            $uniqueError [$warehouseCode][$columnKey] = $ret;
                    }
                }
            }
        }
        if ($uniqueError) {
            $columnMap = $this->fieldMapping();
            foreach ($uniqueError as $warehouseCode => $info) {
                foreach ($info as $columnName => $value) {
                    foreach ($value as $rowIndex => $v) {
                        if ($columnName == 'location_code_back')
                            $this->record($rowIndex, $columnMap [$columnName]['field_name'] . L('与货位编码重复'));
                        else
                            $this->record($rowIndex, $columnMap [$columnName]['field_name'] . L('重复'));
                    }
                }
            }
        }
    }

    /**
     * DB 层数据过滤
     *
     */
    public function filterDbData()
    {
        $warehouseCodes = [];
        foreach ($this->_warehouseInfo as $k => $value) {
            $warehouseCodes [] = $k;
        }
        if ($warehouseCodes)
            $this->getExistedWarehouseInfo($warehouseCodes);
    }

    /**
     * 获取已存在仓库信息
     * @param array $warehouseCodes 仓库编码
     *
     */
    public function getExistedWarehouseInfo($warehouseCodes)
    {
        $warehouseIds = $this->transWarehouseCodes($warehouseCodes);
        // 过滤仓库
        if ($warehouseIds) {
            foreach ($warehouseCodes as $key => $warehouseCode) {
                if (empty($warehouseIds [$warehouseCode])) {
                    foreach ($this->_warehouseInfo [$warehouseCode]['rows'] as $k => $idx) {
                        $this->record($idx, L('仓库编码相关仓库不能存在'));
                    }
                }
            }
        }
        // 如果仓库 sku 无相关数据，则只需要修改重复项即可写入数据库
        $ret = $this->field(['sku', 'warehouse_id', 'location_code', 'location_code_back'])
            ->where(['warehouse_id' => ['in', $warehouseIds]])
            ->select();
        var_dump($ret);exit;
        if ($ret) {

        }
    }

    /**
     * 仓库 CODE 码转仓库 ID
     * @param array $warehouseCodes 仓库编码
     * @return array $ret 仓库编码相关信息
     */
    public function transWarehouseCodes($warehouseCodes)
    {
        $model = new Model();
        $ret = $model->table('tb_wms_warehouse')
            ->where(['CD' => ['in', $warehouseCodes]])
            ->getField('CD, id');

        return $ret;
    }

    /**
     * 数据打包
     *
     */
    public function packData()
    {
        $data = [];
        foreach ($this->data as $index => $info) {
            $temp = [];
            foreach ($info as $key => $value) {
                $temp [$value ['db_field']] = $value ['value'];
            }
            $autoData = $this->create();
            $temp = array_merge($temp, $autoData);
            $data [] = $temp;
        }
        $this->data = $data;
    }

    /**
     * 错误记录
     * @param $key 异常数据
     * @param string $message 提示信息
     * @param string $format 格式化数据
     * @return null
     */
    public function record($key, $message = '', $format = '[%s]')
    {
        $this->errorinfo [][$key] = sprintf($format, $message);;
    }

    /**
     * 导入主入口函数
     *
     */
    public function import()
    {
        parent::import();
        $this->filterCodeData();
        $this->filterDbData();
        $this->packData();
        if (!$this->errorinfo) {
            if ($this->addAll($this->data)) {
                $ret = ['state' => 1, 'msg' => count($this->data)];
            } else {
                $this->errorinfo []['db_error'] = $this->getError();
                $ret = ['state' => 0, 'msg' => $this->errorinfo];
            }

        } else {
            $ret = ['state' => 0, 'msg' => $this->errorinfo];
        }
        return $ret;
    }
}
