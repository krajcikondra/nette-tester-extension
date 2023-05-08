<?php

namespace Helbrary\NetteTesterExtension;


use Nette\Application\IPresenter;
use Nette\Security\IAuthenticator;
use Nette\Utils\Strings;
use Nette\Application\Responses\TextResponse;

abstract class PresenterTester extends Tester
{

    const DEFAULT_USER_ROLE = 'admin';

    /** @var \Nette\DI\Container */
    protected $container;

    /** @var \Nette\Application\LinkGenerator */
    protected $linkGenerator;

    /** @var \Nette\Application\IPresenterFactory */
    protected $presenterFactory;

    /** @var  IAuthenticator */
    protected $authenticator;

    /** @var string */
    protected $userStorageNamespace;


    /**
     * BasePresenterTester constructor.
     * @param string $bootstrapPath
     */
    public function __construct($bootstrapPath = __DIR__ . '/../../../../app/bootstrap.php')
    {
        $this->container = require $bootstrapPath;
        $this->linkGenerator = $this->container->getByType('Nette\Application\LinkGenerator');
        $this->presenterFactory = $this->container->getByType('Nette\Application\IPresenterFactory');
        $this->authenticator = $this->container->getByType(IAuthenticator::class);
    }


    /**
     * Create and send request
     * @param array       $parameters
     * @param string      $method
     * @param null|int|string    $userId
     * @param string      $userRole
     * @param null|array  $identityData
     * @return \Nette\Application\IResponse
     */
    public function sendRequest($parameters = array(), $method = 'GET', $userId = NULL, $userRole = self::DEFAULT_USER_ROLE, $identityData = NULL)
    {
        if ($identityData !== NULL) {
            if ($this->authenticator instanceof Authenticator) {
                $this->authenticator->setIdentityData($identityData);
            } else {
                throw new Exception('Cannot set identityData for active authenticator. Use \Helbrary\NetteTesterExtension\Authenticator instead');
            }
        }

        $presenter = $this->getPresenter($this->presenterName);
        if ($userId !== NULL) {
            $presenter->user->setAuthenticator($this->authenticator);
            if ($this->userStorageNamespace) {
                $presenter->user->getStorage()->setNamespace($this->userStorageNamespace);
            }
            $presenter->user->login($userId, $userRole);

        } else {
            $presenter->user->logOut();
        }
        $request = new \Nette\Application\Request($this->presenterName, $method, $parameters);
        $response = $presenter->run($request);

        if ($response instanceof TextResponse) {
            if ($response->getSource() instanceof \Nette\Application\UI\ITemplate) {
                $output = $response->getSource()->render();
            }
        }
        return $response;
    }


    /**
     * Check if request is without error
     * @param array $parameters
     * @param string $method
     * @param null|int|string $userId
     * @param string $userRole
     * @throws UnexpectedRedirectResponse
     */
    public function checkRequestNoError($parameters = array(), $method = 'GET', $userId = NULL, $userRole = self::DEFAULT_USER_ROLE, $identityData = NULL)
    {
        $this->noError(function() use ($method, $parameters, $userId, $userRole, $identityData) {
            $response = $this->sendRequest($parameters, $method, $userId, $userRole, $identityData);
            if ($response instanceof RedirectResponse) {
                throw new UnexpectedRedirectResponse($response->getUrl());
            }
        });
    }


    /**
     * Check if request is without error
     * @param array $parameters
     * @param string $expectedType
     * @param string $method
     * @param null|int $userId
     * @param string $userRole
     * @throws UnexpectedRedirectResponse
     */
    public function checkRequestError(
        array $parameters,
        string $expectedType,
        string $method = 'GET',
        ?int $userId = NULL,
        string $userRole = self::DEFAULT_USER_ROLE,
        ?array $identityData = NULL
    ) {
        $this->error(function() use ($parameters, $method, $userId, $userRole, $identityData) {
            $response = $this->sendRequest($parameters, $method, $userId, $userRole, $identityData);
            if ($response instanceof RedirectResponse) {
                throw new UnexpectedRedirectResponse();
            }
        }, $expectedType);
    }


    /**
     * @param array  $parameters
     * @param string $redirectToAction - etc. 'Front:Sign:in'
     * @param array  $redirectToActionParameters
     * @param string $method
     * @param int|null    $userId
     * @param string $userRole
     * @param bool   $ignoreRedirectUrlParameters
     * @param array|null   $identityData
     */
    public function checkRedirectTo(
        array $parameters,
        string $redirectToAction,
        array $redirectToActionParameters = [],
        string $method = 'GET',
        ?int $userId = NULL,
        string $userRole = self::DEFAULT_USER_ROLE,
        bool $ignoreRedirectUrlParameters = TRUE,
        ?array $identityData = NULL
    ) {
        $response = $this->sendRequest($parameters, $method, $userId, $userRole, $identityData);
        $this->assertTrue($response instanceof \Nette\Application\Responses\RedirectResponse);
        if ($ignoreRedirectUrlParameters) {
            $responseUrl = $response->getUrl();
            $endPos = strrpos($responseUrl, '?');
            $responseUrlWithoutParameters = Strings::substring($responseUrl, 0, $endPos === FALSE ? NULL : $endPos);
            $this->assertSame($this->linkGenerator->link($redirectToAction, $redirectToActionParameters), $responseUrlWithoutParameters);
        } else {
            $this->assertSame($this->linkGenerator->link($redirectToAction, $redirectToActionParameters), $response->getUrl());
        }
    }


    /**
     * @param string $namespace
     * @return self
     */
    public function setUserStorageNamespace(string $namespace): self
    {
        $this->userStorageNamespace = $namespace;
        return $this;
    }


    /**
     * Return presenter
     * @param string $presenter - etc. 'Front:GoodsChange:Goods'
     * @param bool $autoCanonicalize
     * @return \Nette\Application\IPresenter
     */
    public function getPresenter(string $presenter, $autoCanonicalize = FALSE): IPresenter
    {
        $presenter = $this->presenterFactory->createPresenter($presenter);
        $presenter->autoCanonicalize = $autoCanonicalize;
        return $presenter;
    }


    /**
     * @param string $presenterName
     * @return static
     */
    protected function setPresenterName(string $presenterName): self {
        $this->presenterName = $presenterName;
        return $this;
    }



}
