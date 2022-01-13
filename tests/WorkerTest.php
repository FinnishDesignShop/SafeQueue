<?php


namespace tests\MaxBrokman\SafeQueue;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler as Handler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Worker as IlluminateWorker;
use Illuminate\Queue\WorkerOptions;
use MaxBrokman\SafeQueue\Worker;
use Mockery as m;

class WorkerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var QueueManager|m\MockInterface
     */
    private $queueManager;

    /**
     * @var Queue|m\MockInterface
     */
    private $queue;

    /**
     * @var Dispatcher|m\MockInterface
     */
    private $dispatcher;

    /**
     * @var ManagerRegistry|m\MockInterface
     */
    private $managerRegistry;

    /**
     * @var Connection|m\MockInterface
     */
    private $dbConnection;

    /**
     * @var Repository|m\MockInterface
     */
    private $cache;

    /**
     * @var Handler|m\MockInterface
     */
    private $exceptions;

    /**
     * @var Worker
     */
    private $worker;

    /**
     * @var WorkerOptions
     */
    private $options;

    protected function setUp()
    {
        $this->queueManager  = m::mock(QueueManager::class);
        $this->queue         = m::mock(Queue::class);
        $this->dispatcher    = m::mock(Dispatcher::class);
        $this->managerRegistry = m::mock(ManagerRegistry::class);
        $this->dbConnection  = m::mock(Connection::class);
        $this->cache         = m::mock(Repository::class);
        $this->exceptions    = m::mock(Handler::class);

        $this->worker = new Worker($this->queueManager, $this->dispatcher, $this->managerRegistry, $this->exceptions);

        $this->options = new WorkerOptions(0, 128, 0, 0, 0);

        // Not interested in events
        $this->dispatcher->shouldIgnoreMissing();
    }

    protected function tearDown()
    {
        m::close();
    }

    protected function prepareToRunJob($job)
    {
        if ($job instanceof Job) {
            $jobs = [$job];
        } else {
            $jobs = $job;
        }

        $this->queueManager->shouldReceive('isDownForMaintenance')->andReturn(false);
        $this->queueManager->shouldReceive('connection')->andReturn($this->queue);
        $this->queueManager->shouldReceive('getName')->andReturn('test');

        $this->queue->shouldReceive('pop')->andReturn(...$jobs);
        
        // Create 3 mock entity managers to test.
        $this->managerRegistry->shouldReceive('getManagers')->andReturn([
            'manager1' => m::mock(EntityManager::class),
            'manager2' => m::mock(EntityManager::class),
            'manager3' => m::mock(EntityManager::class),
        ]);
    }

    public function testExtendsLaravelWorker()
    {
        $this->assertInstanceOf(IlluminateWorker::class, $this->worker);
    }

    public function testChecksEmState()
    {
        $job = m::mock(Job::class);
        $job->shouldReceive('fire')->once();
        $job->shouldIgnoreMissing();

        $this->prepareToRunJob($job);
        
        $managers = $this->managerRegistry->getManagers();
        $managerCount = count($managers);

        // Must re-open db connection as many times as there are managers.
        $this->dbConnection->shouldReceive('ping')->times($managerCount)->andReturn(false);
        $this->dbConnection->shouldReceive('close')->times($managerCount);
        $this->dbConnection->shouldReceive('connect')->times($managerCount);

        // Except all managers to receive the same checks.
        foreach ($managers as $em) {
            // EM must return a database connection.
            $em->shouldReceive('getConnection')->andReturn($this->dbConnection);
            // Must make sure EM is open.
            $em->shouldReceive('isOpen')->once()->andReturn(true);
            // EM must be cleared.
            $em->shouldReceive('clear')->once();
        }

        $this->worker->runNextJob('connection', 'queue', $this->options);
    }
}
