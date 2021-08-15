<?php

namespace Box\Mod\Invoice;

class ServiceTransactionTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Invoice\ServiceTransaction
     */
    protected $service = null;

    public function setup(): void
    {
        $this->service = new \Box\Mod\Invoice\ServiceTransaction();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testproccessReceivedATransactions()
    {
        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder(
            "\Box\Mod\Invoice\ServiceTransaction"
        )
            ->setMethods(["getReceived", "preProcessTransaction"])
            ->getMock();
        $serviceMock
            ->expects($this->atLeastOnce())
            ->method("getReceived")
            ->will($this->returnValue([[]]));
        $serviceMock
            ->expects($this->atLeastOnce())
            ->method("preProcessTransaction");

        $dbMock = $this->getMockBuilder("\Box_Database")->getMock();
        $dbMock
            ->expects($this->atLeastOnce())
            ->method("getExistingModelById")
            ->will($this->onConsecutiveCalls($transactionModel));

        $di = new \Box_Di();
        $di["logger"] = new \Box_Log();
        $di["db"] = $dbMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->proccessReceivedATransactions();
        $this->assertTrue($result);
    }

    public function testupdate()
    {
        $eventsMock = $this->getMockBuilder("\Box_EventManager")->getMock();
        $eventsMock->expects($this->atLeastOnce())->method("fire");

        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder("\Box_Database")->getMock();
        $dbMock->expects($this->atLeastOnce())->method("store");

        $di = new \Box_Di();
        $di["db"] = $dbMock;
        $di["events_manager"] = $eventsMock;
        $di["logger"] = new \Box_Log();
        $di["array_get"] = $di->protect(function (
            array $array,
            $key,
            $default = null
        ) use ($di) {
            return isset($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $data = [
            "invoice_id" => "",
            "txn_id" => "",
            "txn_status" => "",
            "gateway_id" => "",
            "amount" => "",
            "currency" => "",
            "type" => "",
            "note" => "",
            "status" => "",
            "validate_ipn" => "",
        ];
        $result = $this->service->update($transactionModel, $data);
        $this->assertTrue($result);
    }

    public function testcreate()
    {
        $eventsMock = $this->getMockBuilder("\Box_EventManager")->getMock();
        $eventsMock->expects($this->atLeastOnce())->method("fire");

        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \RedBeanPHP\OODBBean());

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder("\Box_Database")->getMock();
        $dbMock
            ->expects($this->atLeastOnce())
            ->method("getExistingModelById")
            ->will($this->onConsecutiveCalls($invoiceModel, $payGatewayModel));
        $dbMock
            ->expects($this->atLeastOnce())
            ->method("dispense")
            ->will($this->returnValue($transactionModel));

        $newId = 2;
        $dbMock
            ->expects($this->atLeastOnce())
            ->method("store")
            ->will($this->returnValue($newId));

        $requestMock = $this->getMockBuilder("\Box_Request")->getMock();
        $requestMock->expects($this->atLeastOnce())->method("getClientAddress");

        $di = new \Box_Di();
        $di["db"] = $dbMock;
        $di["events_manager"] = $eventsMock;
        $di["request"] = $requestMock;
        $di["logger"] = new \Box_Log();
        $di["array_get"] = $di->protect(function (
            array $array,
            $key,
            $default = null
        ) use ($di) {
            return isset($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $data = [
            "skip_validation" => false,
            "bb_gateway_id" => 1,
            "bb_invoice_id" => 2,
        ];
        $result = $this->service->create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testcreateInvalidMissinginvoice_id()
    {
        $eventsMock = $this->getMockBuilder("\Box_EventManager")->getMock();
        $eventsMock->expects($this->atLeastOnce())->method("fire");

        $di = new \Box_Di();
        $di["events_manager"] = $eventsMock;

        $this->service->setDi($di);

        $data = [
            "skip_validation" => false,
            "bb_gateway_id" => 1,
        ];

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage("Transaction invoice id is missing");
        $this->service->create($data);
    }

    public function testcreateInvalidMissingbb_gateway_id()
    {
        $eventsMock = $this->getMockBuilder("\Box_EventManager")->getMock();
        $eventsMock->expects($this->atLeastOnce())->method("fire");

        $di = new \Box_Di();
        $di["events_manager"] = $eventsMock;

        $this->service->setDi($di);

        $data = [
            "skip_validation" => false,
            "bb_invoice_id" => 2,
        ];

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage("Payment gateway id is missing");
        $this->service->create($data);
    }

    public function testdelete()
    {
        $dbMock = $this->getMockBuilder("\Box_Database")->getMock();
        $dbMock->expects($this->atLeastOnce())->method("trash");

        $di = new \Box_Di();
        $di["logger"] = new \Box_Log();
        $di["db"] = $dbMock;
        $this->service->setDi($di);

        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->delete($transactionModel);
        $this->assertTrue($result);
    }

    public function testtoApiArray()
    {
        $dbMock = $this->getMockBuilder("\Box_Database")->getMock();
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock
            ->expects($this->atLeastOnce())
            ->method("load")
            ->will($this->returnValue($payGatewayModel));
        $di = new \Box_Di();
        $di["db"] = $dbMock;
        $this->service->setDi($di);

        $expected = [
            "id" => null,
            "invoice_id" => null,
            "txn_id" => null,
            "txn_status" => null,
            "gateway_id" => 1,
            "gateway" => null,
            "amount" => null,
            "currency" => null,
            "type" => null,
            "status" => null,
            "ip" => null,
            "validate_ipn" => null,
            "error" => null,
            "error_code" => null,
            "note" => null,
            "created_at" => null,
            "updated_at" => null,
            "ipn" => null,
        ];
        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \RedBeanPHP\OODBBean());
        $transactionModel->gateway_id = 1;

        $result = $this->service->toApiArray($transactionModel, true);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function searchQueryData()
    {
        return [
            [[], [], "SELECT m.*"],
            [
                ["search" => "keyword"],
                [
                    "note" => "%keyword%",
                    "search__invoice_id" => "%keyword%",
                    "search_txn_id" => "%keyword%",
                    "ipn" => "%keyword%",
                ],
                "AND m.note LIKE :note OR m.invoice_id LIKE :search_invoice_id OR m.txn_id LIKE :search_txn_id OR m.ipn LIKE :ipn",
            ],
            [
                ["invoice_hash" => "hashString"],
                ["hash" => "hashString"],
                "AND i.hash = :hash",
            ],
            [
                ["invoice_id" => "1"],
                ["invoice_id" => "1"],
                "AND m.invoice_id = :invoice_id",
            ],
            [
                ["gateway_id" => "2"],
                ["gateway_id" => "2"],
                "AND m.gateway_id = :gateway_id",
            ],
            [
                ["client_id" => "3"],
                ["client_id" => "3"],
                "AND i.client_id = :client_id",
            ],
            [
                ["status" => "active"],
                ["status" => "active"],
                "AND m.status = :status",
            ],
            [
                ["currency" => "Eur"],
                ["currency" => "Eur"],
                "AND m.currency = :currency",
            ],
            [
                ["type" => "payment"],
                ["type" => "payment"],
                "AND m.type = :type",
            ],
            [
                ["txn_id" => "longTxn_id"],
                ["txn_id" => "longTxn_id"],
                "AND m.txn_id = :txn_id",
            ],
            [
                ["date_from" => "2012-12-12"],
                ["date_from" => 1355270400],
                "AND UNIX_TIMESTAMP(m.created_at) >= :date_from",
            ],
            [
                ["date_to" => "2012-12-12"],
                ["date_to" => 1355270400],
                "AND UNIX_TIMESTAMP(m.created_at) <= :date_to",
            ],
        ];
    }

    /**
     * @dataProvider searchQueryData
     */
    public function testgetSearchQuery(
        $data,
        $expectedParams,
        $expectedStringPart
    ) {
        $di = new \Box_Di();
        $di["array_get"] = $di->protect(function (
            array $array,
            $key,
            $default = null
        ) use ($di) {
            return isset($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);
        $result = $this->service->getSearchQuery($data);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertTrue(strpos($result[0], $expectedStringPart) !== false);
        $this->assertEquals($expectedParams, $result[1]);
    }

    public function testcounter()
    {
        $queryResult = [
            ["status" => \Model_Transaction::STATUS_RECEIVED, "counter" => 1],
        ];
        $dbMock = $this->getMockBuilder("\Box_Database")->getMock();
        $dbMock
            ->expects($this->atLeastOnce())
            ->method("getAll")
            ->will($this->returnValue($queryResult));

        $di = new \Box_Di();
        $di["db"] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->counter();
        $this->assertIsArray($result);
        $expected = [
            "total" => 1,
            "received" => 1,
            "approved" => 0,
            "error" => 0,
            "processed" => 0,
        ];
        $this->assertEquals($expected, $result);
    }

    public function testgetStatusPairs()
    {
        $result = $this->service->getStatusPairs();
        $this->assertIsArray($result);

        $expected = [
            "received" => "Received",
            "approved" => "Approved",
            "processed" => "Processed",
            "error" => "Error",
        ];
        $this->assertEquals($expected, $result);
    }

    public function testgetStatus()
    {
        $result = $this->service->getStatuses();
        $this->assertIsArray($result);

        $expected = [
            "received" => "Received",
            "approved" => "Approved/Verified",
            "processed" => "Processed",
            "error" => "Error",
        ];
        $this->assertEquals($expected, $result);
    }

    public function testgetGatewayStatuses()
    {
        $result = $this->service->getGatewayStatuses();
        $this->assertIsArray($result);

        $expected = [
            "pending" => "Pending validation",
            "complete" => "Complete",
            "unknown" => "Unknown",
        ];
        $this->assertEquals($expected, $result);
    }

    public function testgetTypes()
    {
        $result = $this->service->getTypes();
        $this->assertIsArray($result);

        $expected = [
            "payment" => "Payment",
            "refund" => "Refund",
            "subscription_create" => "Subscription create",
            "subscription_cancel" => "Subscription cancel",
            "unknown" => "Unknown",
        ];
        $this->assertEquals($expected, $result);
    }

    public function testoldProcessLogic()
    {
        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \RedBeanPHP\OODBBean());
        $transactionModel->output = "output String";

        $serviceMock = $this->getMockBuilder(
            "\Box\Mod\Invoice\ServiceTransaction"
        )
            ->setMethods(["process"])
            ->getMock();
        $serviceMock
            ->expects($this->atLeastOnce())
            ->method("process")
            ->will($this->returnValue($transactionModel));

        $result = $serviceMock->oldProcessLogic($transactionModel);
        $this->assertIsString($result);
    }

    public function testpreProcessTransaction()
    {
        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder(
            "\Box\Mod\Invoice\ServiceTransaction"
        )
            ->setMethods(["processTransaction"])
            ->getMock();
        $serviceMock
            ->expects($this->atLeastOnce())
            ->method("processTransaction")
            ->will($this->returnValue("processedOutputString"));

        $eventMock = $this->getMockBuilder("\Box_EventManager")->getMock();
        $eventMock->expects($this->atLeastOnce())->method("fire");

        $di = new \Box_Di();
        $di["events_manager"] = $eventMock;
        $di["logger"] = new \Box_Log();
        $serviceMock->setDi($di);

        $result = $serviceMock->preProcessTransaction($transactionModel);
        $this->assertIsString($result);
    }

    public function testpreProcessTransaction_supportOldLogic()
    {
        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder(
            "\Box\Mod\Invoice\ServiceTransaction"
        )
            ->setMethods(["processTransaction", "oldProcessLogic"])
            ->getMock();
        $serviceMock
            ->expects($this->atLeastOnce())
            ->method("processTransaction")
            ->will(
                $this->throwException(
                    new \Box_Exception(
                        "Exception created with PHPUnit Test",
                        null,
                        705
                    )
                )
            );
        $serviceMock
            ->expects($this->atLeastOnce())
            ->method("oldProcessLogic")
            ->will($this->returnValue("processedOutputString"));

        $eventMock = $this->getMockBuilder("\Box_EventManager")->getMock();
        $eventMock->expects($this->atLeastOnce())->method("fire");

        $di = new \Box_Di();
        $di["events_manager"] = $eventMock;
        $di["logger"] = new \Box_Log();
        $serviceMock->setDi($di);

        $result = $serviceMock->preProcessTransaction($transactionModel);
        $this->assertIsString($result);
    }

    public function testpreProcessTransaction_registerException()
    {
        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \RedBeanPHP\OODBBean());

        $exceptionMessage = "Exception created with PHPUnit Test";

        $serviceMock = $this->getMockBuilder(
            "\Box\Mod\Invoice\ServiceTransaction"
        )
            ->setMethods(["processTransaction", "oldProcessLogic"])
            ->getMock();
        $serviceMock
            ->expects($this->atLeastOnce())
            ->method("processTransaction")
            ->will(
                $this->throwException(new \Box_Exception($exceptionMessage))
            );

        $dbMock = $this->getMockBuilder("\Box_Database")->getMock();
        $dbMock->expects($this->atLeastOnce())->method("store");

        $di = new \Box_Di();
        $di["db"] = $dbMock;
        $serviceMock->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage($exceptionMessage);
        $serviceMock->preProcessTransaction($transactionModel);
    }

    public function paymentsAdapterProvider_withoutProcessTransaction()
    {
        return [
            ["\Payment_Adapter_AliPay"],
            ["\Payment_Adapter_AuthorizeNet"],
            ["\Payment_Adapter_Interkassa"],
            ["\Payment_Adapter_Onebip"],
            ["\Payment_Adapter_Custom"],
        ];
    }

    /**
     * @dataProvider paymentsAdapterProvider_withoutProcessTransaction
     */
    public function testprocessTransactionWithPayment_Adapter($adapter)
    {
        $id = 1;
        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \RedBeanPHP\OODBBean());
        $transactionModel->gateway_id = 2;
        $transactionModel->ipn = "{}";

        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \RedBeanPHP\OODBBean());
        $payGatewayModel->name = substr(
            $adapter,
            strpos($adapter, "\Payment_Adapter_") + 1
        );

        $dbMock = $this->getMockBuilder("\Box_Database")->getMock();
        $dbMock
            ->expects($this->atLeastOnce())
            ->method("load")
            ->will(
                $this->onConsecutiveCalls($transactionModel, $payGatewayModel)
            );

        $paymentAdapterMock = $this->getMockBuilder("\Payment_Adapter_Custom")
            ->disableOriginalConstructor()
            ->getMock();

        $payGatewayService = $this->getMockBuilder(
            "\Box\Mod\Invoice\ServicePayGateway"
        )->getMock();
        $payGatewayService
            ->expects($this->atLeastOnce())
            ->method("getPaymentAdapter")
            ->will($this->returnValue($paymentAdapterMock));

        $di = new \Box_Di();
        $di["db"] = $dbMock;
        $di["mod_service"] = $di->protect(function () use ($payGatewayService) {
            return $payGatewayService;
        });
        $di["api_admin"] = new \Api_Handler(new \Model_Admin());
        $this->service->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage(
            sprintf(
                "Payment adapter %s does not support action %s",
                $payGatewayModel->name,
                "processTransaction"
            )
        );
        $this->service->processTransaction($id);
    }

    public function paymentsAdapterProvider_withprocessTransaction()
    {
        return [
            ["\Payment_Adapter_PayPalEmail"],
            ["\Payment_Adapter_TwoCheckout"],
            ["\Payment_Adapter_WebMoney"],
        ];
    }

    /**
     * @dataProvider paymentsAdapterProvider_withprocessTransaction
     */
    public function testprocessTransaction_supportProcessTransaction($adapter)
    {
        $id = 1;
        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \RedBeanPHP\OODBBean());
        $transactionModel->gateway_id = 2;
        $transactionModel->ipn = "{}";

        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder("\Box_Database")->getMock();
        $dbMock
            ->expects($this->atLeastOnce())
            ->method("load")
            ->will(
                $this->onConsecutiveCalls($transactionModel, $payGatewayModel)
            );

        $paymentAdapterMock = $this->getMockBuilder($adapter)
            ->disableOriginalConstructor()
            ->getMock();

        $paymentAdapterMock
            ->expects($this->atLeastOnce())
            ->method("processTransaction");

        $payGatewayService = $this->getMockBuilder(
            "\Box\Mod\Invoice\ServicePayGateway"
        )->getMock();
        $payGatewayService
            ->expects($this->atLeastOnce())
            ->method("getPaymentAdapter")
            ->will($this->returnValue($paymentAdapterMock));

        $di = new \Box_Di();
        $di["db"] = $dbMock;
        $di["mod_service"] = $di->protect(function () use ($payGatewayService) {
            return $payGatewayService;
        });
        $di["api_system"] = new \Api_Handler(new \Model_Admin());
        $this->service->setDi($di);

        $this->service->processTransaction($id);
    }

    public function getReceived()
    {
        $serviceMock = $this->getMockBuilder(
            "\Box\Mod\Invoice\ServiceTransaction"
        )
            ->setMethods(["getSearchQuery"])
            ->getMock();
        $serviceMock
            ->expects($this->atLeastOnce())
            ->method("getSearchQuery")
            ->will($this->returnValue(["SqlString", []]));

        $assoc = [
            [
                "id" => 1,
                "invoice_id" => 1,
            ],
        ];
        $dbMock = $this->getMockBuilder("\Box_Database")->getMock();
        $dbMock
            ->expects($this->atLeastOnce())
            ->method("load")
            ->will($this->returnValue($assoc));

        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock
            ->expects($this->atLeastOnce())
            ->method("getAll")
            ->will($this->returnValue([[]]));

        $di = new \Box_Di();
        $di["db"] = $dbMock;
        $serviceMock->setDi($di);

        $result = $serviceMock->getReceived();
        $this->assertIsArray($result);
    }

    public function testdebitTransaction()
    {
        $currency = "EUR";
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceModel->currency = $currency;

        $clientModdel = new \Model_Client();
        $clientModdel->loadBean(new \RedBeanPHP\OODBBean());
        $clientModdel->currency = $currency;

        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \RedBeanPHP\OODBBean());
        $transactionModel->amount = 11;

        $clientBalanceModel = new \Model_ClientBalance();
        $clientBalanceModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder("\Box_Database")->getMock();
        $dbMock
            ->expects($this->atLeastOnce())
            ->method("load")
            ->will($this->onConsecutiveCalls($invoiceModel, $clientModdel));
        $dbMock
            ->expects($this->atLeastOnce())
            ->method("dispense")
            ->will($this->returnValue($clientBalanceModel));
        $dbMock->expects($this->atLeastOnce())->method("store");

        $di = new \Box_Di();
        $di["db"] = $dbMock;
        $this->service->setDi($di);

        $this->service->debitTransaction($transactionModel);
    }

    public function testcreateAndProcess()
    {
        $serviceMock = $this->getMockBuilder(
            "\Box\Mod\Invoice\ServiceTransaction"
        )
            ->setMethods(["create", "processTransaction"])
            ->getMock();
        $serviceMock->expects($this->once())->method("create");
        $serviceMock->expects($this->once())->method("processTransaction");

        $ipn = [];
        $serviceMock->createAndProcess($ipn);
    }
}
