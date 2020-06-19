<?php


use Exzachly\LaravelWebsockets\Broadcasting\Broadcasters\LaravelWebsocketBroadcaster;
use Exzachly\LaravelWebsockets\LaravelWebsocketManager;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Http\Request;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class LaravelWebsocketsBroadcasterTest extends TestCase
{
    /**
     * @var LaravelWebsocketBroadcaster
     */
    public $broadcaster;

    public $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = m::mock(LaravelWebsocketManager::class);
        $this->broadcaster = m::mock(LaravelWebsocketBroadcaster::class, [$this->manager, []])->makePartial();
    }

    public function testAuthCallValidAuthenticationResponseWithPrivateChannelWhenCallbackReturnTrue()
    {
        $this->broadcaster->channel('test', function () {
            return true;
        });

        $this->broadcaster->shouldReceive('validAuthenticationResponse')
            ->once();

        $this->broadcaster->auth(
            $this->getMockRequestWithUserForChannel('private-test')
        );
    }

    public function testAuthThrowAccessDeniedHttpExceptionWithPrivateChannelWhenCallbackReturnFalse()
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->broadcaster->channel('test', function () {
            return false;
        });

        $this->broadcaster->auth(
            $this->getMockRequestWithUserForChannel('private-test')
        );
    }

    public function testAuthThrowAccessDeniedHttpExceptionWithPrivateChannelWhenRequestUserNotFound()
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->broadcaster->channel('test', function () {
            return true;
        });

        $this->broadcaster->auth(
            $this->getMockRequestWithoutUserForChannel('private-test')
        );
    }

    public function testAuthCallValidAuthenticationResponseWithPresenceChannelWhenCallbackReturnAnArray()
    {
        $returnData = [1, 2, 3, 4];
        $this->broadcaster->channel('test', function () use ($returnData) {
            return $returnData;
        });

        $this->broadcaster->shouldReceive('validAuthenticationResponse')
            ->once();

        $this->broadcaster->auth(
            $this->getMockRequestWithUserForChannel('presence-test')
        );
    }

    public function testAuthThrowAccessDeniedHttpExceptionWithPresenceChannelWhenCallbackReturnNull()
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->broadcaster->channel('test', function () {
            //
        });

        $this->broadcaster->auth(
            $this->getMockRequestWithUserForChannel('presence-test')
        );
    }

    public function testAuthThrowAccessDeniedHttpExceptionWithPresenceChannelWhenRequestUserNotFound()
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->broadcaster->channel('test', function () {
            return [1, 2, 3, 4];
        });

        $this->broadcaster->auth(
            $this->getMockRequestWithoutUserForChannel('presence-test')
        );
    }

    public function testValidAuthenticationResponseCallPusherSocketAuthMethodWithPrivateChannel()
    {
        $request = $this->getMockRequestWithUserForChannel('private-test');

        $data = [
            'auth' => 'abcd:efgh',
        ];

        $this->manager->shouldReceive('authPrivate')
            ->once()
            ->andReturn(json_encode($data));

        $this->assertEquals(
            $data,
            $this->broadcaster->validAuthenticationResponse($request, true)
        );
    }

    public function testValidAuthenticationResponseCallPusherPresenceAuthMethodWithPresenceChannel()
    {
        $request = $this->getMockRequestWithUserForChannel('presence-test');

        $data = [
            'auth' => 'abcd:efgh',
            'channel_data' => [
                'user_id' => 42,
                'user_info' => [1, 2, 3, 4],
            ],
        ];

        $this->manager->shouldReceive('authPresence')
            ->once()
            ->andReturn(json_encode($data));

        $this->assertEquals(
            $data,
            $this->broadcaster->validAuthenticationResponse($request, true)
        );
    }

    /**
     * @param  string  $channel
     * @return Request
     */
    protected function getMockRequestWithUserForChannel($channel)
    {
        $request = m::mock(Request::class);
        $request->channel_name = $channel;
        $request->socket_id = 'abcd.1234';

        $request->shouldReceive('input')
            ->with('callback', false)
            ->andReturn(false);

        $user = m::mock('User');
        $user->shouldReceive('getAuthIdentifier')
            ->andReturn(42);

        $request->shouldReceive('user')
            ->andReturn($user);

        return $request;
    }

    /**
     * @param  string  $channel
     * @return Request
     */
    protected function getMockRequestWithoutUserForChannel($channel)
    {
        $request = m::mock(Request::class);
        $request->channel_name = $channel;

        $request->shouldReceive('user')
            ->andReturn(null);

        return $request;
    }
}
