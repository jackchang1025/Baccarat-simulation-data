<?php

namespace Weijiajia\BaccaratSimulationData;


use Symfony\Component\Filesystem\Filesystem;

class BaccaratDealer
{
    protected array $deck = []; // 牌堆
    protected array $roundResults = [];
    protected int $cutCardPosition = 0; // 切牌点
    protected int $id = 0;
    protected string $path;

    protected array $playerHand = [];
    protected array $bankerHand = [];

    protected Filesystem $filesystem;

    /**
     * 构造函数，用于初始化牌堆。
     * @param int $decksCount 牌堆中的牌副数，默认为8副。
     */
    public function __construct(protected int $decksCount = 8)
    {
        $this->filesystem = new Filesystem();
        $this->path       = __DIR__.'/../runtime/';
        $this->initializeDeck();
    }

    /**
     * 初始化牌堆。
     */
    protected function initializeDeck(): void
    {
        $this->deck = array_reduce(
            array_fill(0, $this->decksCount, $this->aDeckOfCards()),
            fn($carry, $item) => array_merge($carry, $item),
            []
        );
    }

    /**
     * 生成一副牌
     * @return array
     */
    protected function aDeckOfCards(): array
    {
        //$suits = array_fill(0,4,['♠', '♥', '♣', '♦']);
        return array_reduce(
            array_fill(0, 4, ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K']),
            fn($carry, $item) => array_merge($carry, $item),
            []
        );
    }

    public function getRoundResults(): array
    {
        return $this->roundResults;
    }

    /**
     * 洗牌函数，用于随机打乱牌堆中的牌。
     */
    public function shuffleDeck(): void
    {
        shuffle($this->deck);
    }

    /**
     * 设置切牌点。
     * @param int $position 设置切牌点的位置，即剩余多少张牌时重新洗牌。
     */
    public function setCutCardPosition(int $position): void
    {
        $this->cutCardPosition = $position;
    }

    /**
     * 运行发牌程序
     */
    public function run(): void
    {
        while (!$this->isEnd()) {

            sleep(1);
            $this->id++;
            $result = $this->dealHands();

            echo "轮次 {$this->id}：".PHP_EOL;
            echo "玩家手牌：{$this->formatHand($result['player'])}  {$this->getHandPointsDescription($result['player'])}".PHP_EOL;
            echo "庄家手牌：{$this->formatHand($result['banker'])}  {$this->getHandPointsDescription($result['banker'])}".PHP_EOL;
            echo PHP_EOL;
        }

        $this->save();
        echo "游戏结束！".PHP_EOL;
    }

    /**
     * 是否达到切牌点（是否结束这一局）
     * @return bool
     */
    public function isEnd(): bool
    {
        return $this->cutCardPosition && count($this->deck) <= $this->cutCardPosition;
    }

    /**
     * 处理一轮发牌。
     * @return array 返回包含玩家和庄家手牌的数组。
     */
    public function dealHands(): array
    {
        $playerHand = array($this->dealCard(), $this->dealCard());
        $bankerHand = array($this->dealCard(), $this->dealCard());

        // 根据规则决定是否发第三张牌
        if ($this->shouldDealThirdCard($playerHand, $bankerHand)) {
            $playerHand[] = $this->dealCard();
        }

        if ($this->shouldDealThirdCard($bankerHand, $playerHand, true)) {
            $bankerHand[] = $this->dealCard();
        }

        // 使用 UUID 作为每轮结果的唯一标识符
        $result               = ['id' => $this->id, 'player' => $playerHand, 'banker' => $bankerHand];
        $this->roundResults[] = $result; // 记录结果

        return $result;
    }

    /**
     * 发牌函数，用于给玩家和庄家各发两张牌。
     * @return string 返回一个包含玩家和庄家手牌的数组。
     */
    private function dealCard(): string
    {
        return array_shift($this->deck);
    }

    /**
     * 根据规则决定是否发第三张牌
     * @param array $hand
     * @param array $otherHand
     * @param bool $isBanker
     * @return bool
     */
    protected function shouldDealThirdCard(array $hand, array $otherHand, bool $isBanker = false): bool
    {
        $handPoints      = $this->calculatePoints($hand);
        $otherHandPoints = $this->calculatePoints($otherHand);

        if ($handPoints >= 8 || $otherHandPoints >= 8) {
            // 如果任一方为自然赢家，则不再发牌
            return false;
        }

        if ($isBanker) {
            // 庄家的发牌规则
            if (count($hand) == 2) {
                // 如果庄家只有两张牌
                if ($handPoints <= 2) {
                    return true;
                }
                if ($handPoints == 3 && (!isset($otherHand[2]) || $otherHand[2] != '8')) {
                    return true;
                }
                if ($handPoints == 4 && (!isset($otherHand[2]) || in_array(
                            $otherHand[2],
                            array('2', '3', '4', '5', '6', '7')
                        ))) {
                    return true;
                }
                if ($handPoints == 5 && (!isset($otherHand[2]) || in_array($otherHand[2], array('4', '5', '6', '7')))) {
                    return true;
                }
                if ($handPoints == 6 && (!isset($otherHand[2]) || in_array($otherHand[2], array('6', '7')))) {
                    return true;
                }
            }

            return false;
        } else {
            // 玩家的发牌规则
            return $handPoints <= 5;
        }
    }

    /**
     * 计算手牌点数
     * @param array $hand
     * @return int
     */
    function calculatePoints(array $hand): int
    {
        $points = array_reduce(
            $hand,
            fn($carry, $item) => is_numeric($item)
                ? $carry + $item
                : $carry + ['A' => 1, 'J' => 0, 'Q' => 0, 'K' => 0,][$item],
            0
        );

        return $points % 10;
    }

    /**
     * 格式化显示手牌
     * @param array $hand 一手牌
     * @return string 格式化后的手牌字符串
     */
    private function formatHand(array $hand): string
    {
        return implode(', ', $hand);
    }

    /**
     * 获取手牌的点数描述
     * @param array $hand 一手牌
     * @return string 点数描述
     */
    private function getHandPointsDescription(array $hand): string
    {
        return '点数：'.$this->calculatePoints($hand);
    }

    protected function save(): void
    {
        $savePath = "$this->path/{$this->fileName()}";

        // 确保路径存在
        if (!$this->filesystem->exists($this->path)) {
            $this->filesystem->mkdir($this->path);
        }

        // 保存结果到文件
        $this->filesystem->dumpFile($savePath, json_encode($this->roundResults));
    }

    protected function fileName(): string
    {
        return time().'.json';
    }
}
