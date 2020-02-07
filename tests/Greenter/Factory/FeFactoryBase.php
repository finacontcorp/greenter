<?php
/**
 * Created by PhpStorm.
 * User: Giansalex
 * Date: 26/07/2017
 * Time: 23:52
 */

namespace Tests\Greenter\Factory;

use Greenter\Builder\BuilderInterface;
use Greenter\Factory\FeFactory;
use Greenter\Model\Client\Client;
use Greenter\Model\DocumentInterface;
use Greenter\Model\Response\BaseResult;
use Greenter\Model\Sale\Charge;
use Greenter\Model\Sale\Document;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Model\Sale\Note;
use Greenter\Model\Summary\Summary;
use Greenter\Model\Summary\SummaryDetail;
use Greenter\Model\Summary\SummaryPerception;
use Greenter\Model\Voided\Voided;
use Greenter\Model\Voided\VoidedDetail;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Ws\Services\BillSender;
use Greenter\Ws\Services\ExtService;
use Greenter\Services\SenderInterface;
use Greenter\Ws\Services\SoapClient;
use Greenter\Ws\Services\SummarySender;
use Greenter\Ws\Services\SunatEndpoints;
use Greenter\Xml\Builder\InvoiceBuilder;
use Greenter\Xml\Builder\NoteBuilder;
use Greenter\Xml\Builder\SummaryBuilder;
use Greenter\Xml\Builder\VoidedBuilder;
use Greenter\XMLSecLibs\Sunat\SignedXml;

/**
 * Trait FeFactoryTrait
 * @package Tests\Greenter
 */
class FeFactoryBase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FeFactory
     */
    protected $factory;

    /**
     * @var array
     */
    protected $builders;

    public function setUp()
    {
        $this->builders = [
            Invoice::class => InvoiceBuilder::class,
            Note::class => NoteBuilder::class,
            Summary::class => SummaryBuilder::class,
            Voided::class => VoidedBuilder::class,
        ];

        $signer = new SignedXml();
        $signer->setCertificateFromFile(__DIR__.'/../../Resources/SFSCert.pem');

        $factory = new FeFactory();
        $factory->setSigner($signer);
        $this->factory = $factory;
    }

    /**
     * @param string $className
     * @param string $endpoint
     * @return SenderInterface
     */
    private function getSender($className, $endpoint)
    {
        $client = new SoapClient(SunatEndpoints::WSDL_ENDPOINT);
        $client->setCredentials('20000000001MODDATOS', 'moddatos');
        $client->setService($endpoint);
        $summValids = [Summary::class, Summary::class, Voided::class];
        $sender = in_array($className, $summValids) ? new SummarySender(): new BillSender();
        $sender->setClient($client);

        return $sender;
    }

    /**
     * @param DocumentInterface $document
     * @return BaseResult|\Greenter\Model\Response\BillResult|\Greenter\Model\Response\SummaryResult
     */
    protected function getFactoryResult(DocumentInterface $document)
    {
        $sender = $this->getSender(get_class($document), SunatEndpoints::FE_BETA);
        $builder = $this->getBuilder($document);
        $factory = $this->factory
            ->setBuilder($builder)
            ->setSender($sender);

        return $factory->send($document);
    }

    /**
     * @param DocumentInterface $document
     * @return BuilderInterface
     */
    protected function getBuilder(DocumentInterface $document) {
        return new $this->builders[get_class($document)]([
            'cache' => false,
            'strict_variables' => true,
        ]);
    }

    /**
     * @return ExtService
     */
    protected function getExtService()
    {
        $client = new SoapClient(SunatEndpoints::WSDL_ENDPOINT);
        $client->setCredentials('20000000001MODDATOS', 'moddatos');
        $client->setService(SunatEndpoints::FE_BETA);
        $service = new ExtService();
        $service->setClient($client);

        return $service;
    }

    protected function getInvoice()
    {
        $client = new Client();
        $client->setTipoDoc('6')
            ->setNumDoc('20000000001')
            ->setRznSocial('EMPRESA 1');

        $invoice = new Invoice();
        $invoice->setFecVencimiento(new \DateTime())
            ->setCompra('01-21312312')
            ->setTipoDoc('01')
            ->setSerie('F001')
            ->setCorrelativo('123')
            ->setFechaEmision($this->getDate())
            ->setTipoMoneda('PEN')
            ->setClient($client)
            ->setMtoOperGravadas(200)
            ->setMtoOperExoneradas(0)
            ->setMtoOperInafectas(0)
            ->setMtoIGV(36)
            ->setMtoImpVenta(2236.43)
            ->setCompany($this->getCompany());

        $detail1 = new SaleDetail();
        $detail1->setCodProducto('C023')
            ->setUnidad('NIU')
            ->setCantidad(2)
            ->setDescuento(1)
            ->setDescripcion('PROD 1')
            ->setIgv(18)
            ->setTipAfeIgv('10')
            ->setMtoValorVenta(100)
            ->setMtoValorUnitario(50)
            ->setMtoPrecioUnitario(56);

        $detail2 = new SaleDetail();
        $detail2->setCodProducto('C02')
            ->setCodProdSunat('012')
            ->setUnidad('NIU')
            ->setCantidad(2)
            ->setDescripcion('PROD 1')
            ->setIgv(18)
            ->setTipAfeIgv('10')
            ->setMtoValorVenta(100)
            ->setMtoValorUnitario(50)
            ->setMtoPrecioUnitario(56);

        $legend = new Legend();
        $legend->setCode('1000')
            ->setValue('SON N SOLES');

        $invoice->setDetails([$detail1, $detail2])
            ->setLegends([$legend]);

        return $invoice;
    }

    protected function getInvoiceV21()
    {
        $client = new Client();
        $client->setTipoDoc('6')
            ->setNumDoc('20000000001')
            ->setRznSocial('EMPRESA 1');

        $invoice = new Invoice();
        $invoice->setFecVencimiento(new \DateTime())
            ->setTipoOperacion('0101')
            ->setTipoDoc('01')
            ->setSerie('F001')
            ->setCorrelativo('123')
            ->setFechaEmision($this->getDate())
            ->setTipoMoneda('PEN')
            ->setClient($client)
            ->setCompra('01-21312312')
            ->setMtoOperGravadas(200)
            ->setMtoIGV(36)
            ->setTotalImpuestos(36)
            ->setValorVenta(200)
            ->setMtoImpVenta(236.43)
            ->setCompany($this->getCompany());

        $detail1 = new SaleDetail();
        $detail1->setCodProducto('C023')
            ->setUnidad('NIU')
            ->setCantidad(2)
            ->setDescuentos([
                (new Charge())
                ->setCodTipo('00')
                ->setFactor(1.00)
                ->setMontoBase(100.00)
                ->setMonto(1.00)
            ])
            ->setDescripcion('PROD 1')
            ->setMtoBaseIgv(100)
            ->setPorcentajeIgv(18.00)
            ->setIgv(18)
            ->setTipAfeIgv('10')
            ->setTotalImpuestos(18.00)
            ->setMtoValorVenta(100)
            ->setMtoValorUnitario(50)
            ->setMtoPrecioUnitario(56);

        $legend = new Legend();
        $legend->setCode('1000')
            ->setValue('SON N SOLES');

        $invoice->setDetails([$detail1])
            ->setLegends([$legend]);

        return $invoice;
    }

    protected function getCreditNote()
    {
        $client = new Client();
        $client->setTipoDoc('6')
            ->setNumDoc('20000000001')
            ->setRznSocial('EMPRESA 1');

        $note = new Note();
        $note
            ->setTipDocAfectado('01')
            ->setNumDocfectado('F001-111')
            ->setCodMotivo('07')
            ->setDesMotivo('ANULACION DE LA OPERACION')
            ->setTipoDoc('07')
            ->setSerie('FF01')
            ->setFechaEmision($this->getDate())
            ->setCorrelativo('123')
            ->setTipoMoneda('PEN')
            ->setClient($client)
            ->setMtoOperGravadas(200)
            ->setMtoOperExoneradas(0)
            ->setMtoOperInafectas(0)
            ->setMtoIGV(36)
            ->setMtoImpVenta(236)
            ->setCompany($this->getCompany());

        $detail1 = new SaleDetail();
        $detail1->setCodProducto('C023')
            ->setUnidad('NIU')
            ->setCantidad(2)
            ->setDescripcion('PROD 1')
            ->setIgv(18)
            ->setTipAfeIgv('10')
            ->setMtoValorVenta(100)
            ->setMtoValorUnitario(50)
            ->setMtoPrecioUnitario(56);

        $detail2 = new SaleDetail();
        $detail2->setCodProducto('C02')
            ->setUnidad('NIU')
            ->setCantidad(2)
            ->setDescripcion('PROD 2')
            ->setIgv(18)
            ->setTipAfeIgv('10')
            ->setMtoValorVenta(100)
            ->setMtoValorUnitario(50)
            ->setMtoPrecioUnitario(56);

        $legend = new Legend();
        $legend->setCode('1000')
            ->setValue('SON N SOLES');

        $note->setDetails([$detail1, $detail2])
            ->setLegends([$legend]);

        return $note;
    }

    protected function getCreditNoteV21()
    {
        $client = new Client();
        $client->setTipoDoc('6')
            ->setNumDoc('20000000001')
            ->setRznSocial('EMPRESA 1');

        $note = new Note();
        $note
            ->setTipDocAfectado('01')
            ->setNumDocfectado('F001-111')
            ->setCodMotivo('07')
            ->setDesMotivo('ANULACION DE LA OPERACION')
            ->setTipoDoc('07')
            ->setSerie('FF01')
            ->setFechaEmision($this->getDate())
            ->setCorrelativo('123')
            ->setTipoMoneda('PEN')
            ->setClient($client)
            ->setMtoOperGravadas(200)
            ->setMtoIGV(36)
            ->setTotalImpuestos(36)
            ->setMtoImpVenta(236)
            ->setCompany($this->getCompany());

        $detail = new SaleDetail();
        $detail->setCodProducto('C023')
            ->setUnidad('NIU')
            ->setCantidad(2)
            ->setDescripcion('PROD 1')
            ->setMtoBaseIgv(100)
            ->setPorcentajeIgv(18.00)
            ->setIgv(18)
            ->setTipAfeIgv('10')
            ->setTotalImpuestos(18)
            ->setMtoValorVenta(100)
            ->setMtoValorUnitario(50)
            ->setMtoPrecioUnitario(56);

        $legend = new Legend();
        $legend->setCode('1000')
            ->setValue('SON N SOLES');

        $note->setDetails([$detail])
            ->setLegends([$legend]);

        return $note;
    }

    protected function getDebitNote()
    {
        $debit = $this->getCreditNote();
        $debit->setCodMotivo('01')
            ->setDesMotivo('XXXX ')
            ->setTipoDoc('08')
            ->setFechaEmision($this->getDate());

        return $debit;
    }

    protected function getDebitNoteV21()
    {
        $debit = $this->getCreditNoteV21();
        $debit->setCodMotivo('01')
            ->setDesMotivo('XXXX ')
            ->setTipoDoc('08')
            ->setFechaEmision($this->getDate());

        return $debit;
    }

    protected function getSummary()
    {
        $detiail1 = new SummaryDetail();
        $detiail1->setTipoDoc('07')
            ->setSerieNro('B001-12')
            ->setClienteTipo('1')
            ->setClienteNro('44556677')
            ->setEstado('1')
            ->setDocReferencia((new Document())
                ->setTipoDoc('03')
                ->setNroDoc('B001-1'))
            ->setTotal(100)
            ->setMtoOperGravadas(20.555)
            ->setMtoOperInafectas(12)
            ->setMtoOperExoneradas(15)
            ->setMtoIGV(3.6);

        $detiail2 = new SummaryDetail();
        $detiail2->setTipoDoc('03')
            ->setSerieNro('B001-22')
            ->setClienteTipo('1')
            ->setClienteNro('55667733')
            ->setPercepcion((new SummaryPerception())
                ->setCodReg('01')
                ->setTasa(2.00)
                ->setMtoBase(200.00)
                ->setMto(4.00)
                ->setMtoTotal(204.00))
            ->setEstado('1')
            ->setTotal(200)
            ->setMtoOperGravadas(3)
            ->setMtoOperExoneradas(30)
            ->setMtoOperInafectas(2)
            ->setMtoOtrosCargos(1)
            ->setMtoIGV(7.2)
            ->setMtoISC(2.8);

        $sum = new Summary();
        $sum->setFecGeneracion($this->getDate())
            ->setFecResumen($this->getDate())
            ->setCorrelativo('001')
            ->setCompany($this->getCompany())
            ->setDetails([$detiail1, $detiail2]);

        return $sum;
    }

    protected function getVoided()
    {
        $detial1 = new VoidedDetail();
        $detial1->setTipoDoc('01')
            ->setSerie('F001')
            ->setCorrelativo('02132132')
            ->setDesMotivoBaja('ERROR DE SISTEMA');

        $detial2 = new VoidedDetail();
        $detial2->setTipoDoc('07')
            ->setSerie('FC01')
            ->setCorrelativo('123')
            ->setDesMotivoBaja('ERROR DE RUC');

        $voided = new Voided();
        $voided->setCorrelativo('001')
            ->setFecComunicacion($this->getDate())
            ->setFecGeneracion($this->getDate())
            ->setCompany($this->getCompany())
            ->setDetails([$detial1, $detial2]);

        return $voided;
    }

    /**
     * @return Company
     */
    private function getCompany()
    {
        $company = new Company();
        $address = new Address();
        $address->setUbigueo('150101')
            ->setDepartamento('LIMA')
            ->setProvincia('LIMA')
            ->setDistrito('LIMA')
            ->setUrbanizacion('NONE')
            ->setDireccion('AV LS');
        $company->setRuc('20000000001')
            ->setRazonSocial('EMPRESA SAC')
            ->setNombreComercial('EMPRESA')
            ->setAddress($address);

        return $company;
    }

    private function getDate()
    {
        $date = new \DateTime();
        try {
            $date->sub(new \DateInterval('P1D'));
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

        return $date;
    }
}