<?php

/**
 * 操作excel模型层
 * 
 */
class ExcelOperationModel extends Model
{   
    /**
     * @var int $chunk_size  每次读取多少行，默认配置500行
     */
    public $chunk_size = 10;
    
    /**
     * @var String $excel_path 文件位置
     */
    public $excel_path;
    
    /**
     * @var boolean $is_read_only 是否只读数据，对于大的Excel表优化有很大作用
     */
    public $is_read_only = true;
    
    /**
     * @var Object $obj_excel 将一个Excel文件，加载读取后成为一个可操作对象
     */
    public $obj_excel;
    
    /**
     * @var int $start_row 从哪一行开始读取，一般为2。有特殊需求可修改此值
     */
    public $start_row = 2;
    
    /**
     * @var Object $obj_reader 一个读取器，用于初始化Excel组件
     */
    public $obj_reader;
    
    /**
     * @var int $max_length Excel表最大行
     */
    public $max_row;
    
    /**
     * @var int $max_cloumn Excel表最大列
     */
    public $max_cloumn;
    
    /**
     * @var String $input_file_type 文件类型
     */
    public $input_file_type;
    
    /**
     * @var int 工作区，默认为第一页
     */
    public $sheet = 0;

    public $max_column_int = 0;
    
    private $extension= [
        'xls', 'xlsx'
    ];
    
    /**
     * Excel组件初始化
     * 
     * 构造函数，用于Excel插件的导入与配置
     * ExcelOperationModel constructor.
     * @param string $excel_path Excel文件路径
     * @throws Exception
     */
    public function __construct($excel_path)
    {
        if (in_array($this->detectUploadFileMIME(), $this->extension) and $this->detectUploadFileMIME() !== 0) {
            vendor("PHPExcel.PHPExcel");
            $this->excel_path = $excel_path;
            $this->input_file_type = PHPExcel_IOFactory::identify($this->excel_path);
            $this->obj_reader = PHPExcel_IOFactory::createReader($this->input_file_type);
            $this->obj_excel = $this->obj_reader->load($this->excel_path);
            $this->sheet = $this->obj_excel->getSheet(0);
            $this->max_row = $this->sheet->getHighestRow();
            $this->max_cloumn = $this->sheet->getHighestColumn();
            $this->getExcelColumn();
        } else {
            throw new \Exception('只支持xls,xlsx格式文件');
        }
    }
    
    /**
     * Excel读取参数设置
     * 目前只写了是否忽略格式读取
     * 
     */
    public function setOptions()
    {
        $this->obj_reader->setReadDataOnly($this->is_read_only);
    }


    /**
     * 获取Excel宽度
     * @return Int $excel_column
     *
     */
    public function getExcelColumn()
    {
        $letterLen = 26;
        $commonLetterLen = ord('A');
        $excel_column = $this->max_cloumn;
        $strlen = strlen($excel_column);
        if ($strlen > 1) $this->max_column_int = (ord($excel_column{0}) - $commonLetterLen + 1) * $letterLen + ord($excel_column{1}) - $commonLetterLen + 1;
        else $this->max_column_int = ord($excel_column) - $commonLetterLen + 1;
    }
    
    /**
     * 检查文件格式
     * 
     */
    private function detectUploadFileMIME() {
        $file = $_FILES ['file'];
        $flag = 0;
        $file_array = explode(".", $file ["name"]);
        $file_extension = strtolower (array_pop ($file_array));
        switch ($file_extension) {  
            case "xls" :// 2003 excel
                $fh = fopen ($file ["tmp_name"], "rb");
                $bin = fread ($fh, 8);  
                fclose($fh);
                $strinfo = @unpack("C8chars", $bin);
                $typecode = "";
                foreach ($strinfo as $num) {
                    $typecode .= dechex ($num);
                }
                if ($typecode == "d0cf11e0a1b11ae1") {
                    $flag = 1;
                }
                break;
            case "xlsx" :
                // 2007 excel
                $fh = fopen ($file ["tmp_name"], "rb");
                $bin = fread ($fh, 4);
                fclose ($fh);
                $strinfo = @unpack("C4chars", $bin);
                $typecode = "";
                foreach ($strinfo as $num) {
                    $typecode .= dechex ($num);
                }
                if ($typecode == "504b34") {
                    $flag = 1;
                }
                break;
        }
        if ($flag) return $file_extension;
        return $flag;
    }
}

vendor("PHPExcel.PHPExcel");
/**
 * 分块读取类
 *
 */
class chunkReadFilter implements PHPExcel_Reader_IReadFilter
{
    private $_startRow = 0;     // 开始行
    private $_endRow = 0;       // 结束行
    public function __construct($startRow, $chunkSize) {    // 我们需要传递：开始行号&行跨度(来计算结束行号)
        $this->_startRow = $startRow;
        $this->_endRow       = $startRow + $chunkSize;
    }
    
    public function setRows($startRow, $chunkSize) {
        $this->_startRow = $startRow;
        $this->_endRow       = $startRow + $chunkSize;
    }
    
    public function getEndRow()
    {
        return $this->_endRow;
    }
    
    public function getStartRow()
    {
        return $this->_startRow;
    }
    
    public function readCell($column, $row, $worksheetName = '') {
        if (($row == 1) || ($row >= $this->_startRow && $row < $this->_endRow)) {
            return true;
        }
        return false;
    }
}
