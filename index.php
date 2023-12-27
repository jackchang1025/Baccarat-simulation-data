<?php

use Weijiajia\BaccaratSimulationData\BaccaratDealer;

require_once __DIR__. '/vendor/autoload.php';



// 使用类
$dealer = new BaccaratDealer();
$dealer->shuffleDeck();
$dealer->setCutCardPosition(52); // 设置切牌点，例如在剩余52张牌时重洗
$dealer->run();
