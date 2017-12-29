<?php
/**
 * EXCEL导入基类
 * 必须要重写的两个方法valid和import
 * valid可以自己扩展验证规则，会对每一个格进行验证
 * import在继承的情况下重写，会生成title,data,errorinfo数据，可根据errorinfo进行excel的处理
 * 
 */
class BaseImportExcelModel extends BaseModel
{
    public $excel;
    public $data;
    public $title;
    public $errorinfo;
    
    /**
     * 数据库字段映射值
     * 读取出来的EXCEL会根据下面的字段配置生成如以下格式的数据
     * [
     *     '行' => [
     *         [
     *             '列' => [
     *                 'db_field' => 'SP_NAME',
     *                 'value'    => 'Excel中对应的坐标的值'
     *             ]
     *         ]
     *     ]
     * ]
     * 
     */
    public function fieldMapping()
    {
        return [
            'SP_NAME' => ['field_name' => '供应商名称/Supplier name', 'required' => true],
            'SP_RES_NAME' => ['field_name' => '供应商简称(选填)/Supplier abbr.(optional)', 'required' => false],
            'SP_NAME_EN' => ['field_name' => '英文名称/English name', 'required' => false],
            'SP_RES_NAME_EN' => ['field_name' => '英文简称/English abbr.', 'required' => false],
            'SP_CHARTER_NO' => ['field_name' => '营业执照号/Business license No.', 'required' => false],
            'COPANY_TYPE_CD' => ['field_name' => '企业类型/Enterprise type', 'required' => false],
            'SP_YEAR_SCALE_CD' => ['field_name' => '供应商年业务规模/Annual business scale', 'required' => false],
            'SP_TEAM_CD1' => ['field_name' => '采购团队1/Procurement team1', 'required' => false],
            'SP_TEAM_CD2' => ['field_name' => '采购团队2/Procurement team2', 'required' => false],
            'SP_TEAM_CD3' => ['field_name' => '采购团队3/Procurement team3', 'required' => false],
            'SP_JS_TEAM_CD' => ['field_name' => '介绍团队/Recommend team', 'required' => false],
            'COMPANY_ADDR_INFO' => ['field_name' => '办公详细地址/Office Detail address', 'required' => false],
            'COMPANY_MARKET_INFO' => ['field_name' => '公司与市场地位简述/Company notes', 'required' => false],
            'SP_REMARK' => ['field_name' => '备注(选填)/Remark(optional)', 'required' => false],
        ];
    }
    
    /**
     * 加载EXCEL，生成excel对象
     * 
     */
    public function loadExcel()
    {
        $filePath = $_FILES['file']['tmp_name'];
        $this->excel = new ExcelOperationModel($filePath);
    }

    /**
     * 测试获取title
     *
     */
    public function getCellData($currentRow)
    {
        $base = $index = $point = 'A';
        for ($i = 1; $i <= $this->excel->max_column_int; $i ++) {
            $name [$index] = trim((string)$this->excel->sheet->getCell($index . $currentRow)->getValue());
            $index ++;
            if ($i % 26 == 0) {
                $index = $base . $point;
                $base++;
            }
        }
        return $name;
    }
    
    /**
     * 根据EXCEL的标题与fieldMapping生成如下格式数据
     *  [
     *      'A' => [
     *          'db_field' => 'SP_NAME',
     *          'required' => 'true/false',
     *          'en_name'  => 'EXCEL标题名'
     *      ]
     *  ]
     *
     */
    public function getTitle()
    {
        $currentRow = 1;
        $name = $this->getCellData($currentRow);

        $fields = $this->fieldMapping();
        foreach ($name as $key => $value) {
            foreach ($fields as $k => $v) {
                if ($v ['field_name'] == $value) {
                    $temp [$key] ['db_field'] = $k;
                    $temp [$key] ['required'] = $v['required'];
                    $temp [$key] ['en_name'] = $value;
                }
            }
        }
        $this->title = $temp;
    }
    
    /**
     * 校验是否不能为空，根据filedMapping生成最基本的验证规则
     * 如果要增加自己的验证规则，则需要继承并重写该方法
     * @param $row 行坐标
     * @param $column 列坐标
     * @param $value 值
     * @return null
     */
    public function valid($row_index, $column_index, $value)
    {
        $db_field = $this->title [$column_index]['db_field'];//重写该方法的时候，必须保留这一句
        // 必填验证
        if ($this->title [$column_index]['required'] and empty($value)) $this->errorinfo [][$row_index.$column_index] = $this->title [$column_index]['en_name'] . ' 必填(require)';
    }
    
    /**
     * 读取excel中的数据
     * 
     */
    public function getData()
    {
        $data = [];
        // read
        for ($this->excel->start_row; $this->excel->start_row <= $this->excel->max_row; $this->excel->start_row ++) {
            $data [$this->excel->start_row] = $this->getCellData($this->excel->start_row);
        }
        // save
        $r = [];
        foreach ($data as $key => $value) {
            $temp = [];
            foreach ($value as $k => $v) {
                $temp [$k]['db_field'] = $this->title [$k]['db_field'];
                $temp [$k]['value'] = $v;
            }
            $r [$key] = $temp;
        }
        $this->data = $r;
        unset($data, $r);
    }
    
    /**
     * 处理数据，验证数据，自由增加规则验证，继承重写该方法，如果需要基础的必填验证则需要实现父类的验证方法
     * 
     */
    public function processData()
    {
        foreach ($this->data as $key => $val) {
            foreach ($val as $k => $v) {
                $this->valid($key, $k, $v ['value']);    
            }
        }
    }
    
    /**
     * 导入主入口函数
     * 可访问数据$this->title EXCEL标题名与表字段对应的映射
     * $this->data 读取完后生成的数据
     * $this->errorinfo 根据验证规则生成的错误提示信息
     */
    public function import()
    {
        try {
            //加载excel
            $this->loadExcel();
            //获取标题
            $this->getTitle();
            //数据加载
            $this->getData();
            //数据验证
            $this->processData();
        } catch (\Exception $e) {
            $this->errorinfo []['error'] = $e->getMessage();
        }
    }
}
