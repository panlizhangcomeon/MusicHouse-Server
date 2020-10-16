<?php
namespace App\Process;

use EasySwoole\Component\Process\AbstractProcess;
use App\Service\TianTianApi;
use EasySwoole\Component\Timer;
use EasySwoole\Utility\ArrayToTextTable;

/**
 * 查看基金估值并格式化输出
 * Class FundProcess
 * @package App\Process
 */
class FundProcess extends AbstractProcess {

    protected $fundCodeArr = [
        '003634', '002542', '005918', '161726', '001302', '001740', '003096', '005609', '000311',
        '004744', '161028', '001071', '004857', '163406', '001511', '398061', '161725', '320007'
    ]; //自选基金代码

    protected $apiResult = [];

    protected function run($arg)
    {
        // TODO: Implement run() method.
        go(function () {
            Timer::getInstance()->loop(60 * 1000, function () {
                $this->getFundData();
            });
        });
    }

    /**
     * 获取基金对应估值并排序，格式化输出
     */
    function getFundData() {
        $startTime = microtime(true);

        $unixTimeStamp = $this->getUnixTimeStamp();
        foreach ($this->fundCodeArr as $fundCode) {
            $path = 'js/' . $fundCode . '.js?rt=' . $unixTimeStamp;
            $data = TianTianApi::getInstance()->request($path);
            $status = '';
            if (isset($this->apiResult[$fundCode]['gszzl'])) {
                if ($data['gszzl'] > $this->apiResult[$fundCode]['gszzl']) {
                    $status = "\e[31m" . str_pad('↑', 10, ' ') . "\e[0m";
                } else if ($data['gszzl'] < $this->apiResult[$fundCode]['gszzl']) {
                    $status = "\e[32m" . str_pad('↓', 10, ' ') . "\e[0m";
                } else {
                    $status = "\e[32m" . str_pad(' ', 10, ' ') . "\e[0m";
                }
            }
            $this->apiResult[$fundCode] = [
                'name' => $data['name'],
                'gszzl' => $data['gszzl'],
                'gztime' => $data['gztime'],
                'status' => $status
            ];
        }
        $result = $this->apiResult;
        //根据估值排序
        array_multisort(array_column($result, 'gszzl'), SORT_DESC, $result);
        foreach ($result as $item => &$value) {
            $status = $value['status'] ?? '';
            $value['gszzl'] = $this->displayItem($value['gszzl']) . ' ' . $status;
            $gzTime = $value['gztime'] ?? date("Y-m-d H:i:s");
            unset($value['gztime']);
            unset($value['status']);
        }

        echo "本次查询时间：" . $gzTime . PHP_EOL;

        $render = new ArrayToTextTable($result);
        $render->setIndentation("\t");
        $render->isDisplayHeader(true);
        $render->setKeysAlignment(ArrayToTextTable::AlignLeft);
        $render->setValuesAlignment(ArrayToTextTable::AlignLeft);
        $table = $render->getTable();
        echo $table;

        $endTime = microtime(true);
        echo "本次执行耗时：" . ($endTime - $startTime) . '秒' . PHP_EOL;
    }

    function displayItem($valuation) {
        $colorAscII = '';
        if ($valuation > 0) {
            $colorAscII = "[31m";
        } else if ($valuation < 0) {
            $colorAscII = "[32m";
        }
        return "\e" . $colorAscII . $valuation . "\e[0m";
    }

    /**
     * 获取13位时间戳
     * @return int
     */
    public function getUnixTimeStamp() {
        list($var1, $var2) = explode(' ', microtime());
        return (int)sprintf("%.0f", (floatval($var1) + floatval($var2)) * 1000);
    }
}
