<?php

namespace CoreShop\Payum\StripeBundle\Extension;

use CoreShop\Bundle\PayumBundle\Factory\GetStatusFactoryInterface;
use CoreShop\Bundle\WorkflowBundle\Manager\StateMachineManagerInterface;
use CoreShop\Component\Core\Model\PaymentInterface;
use CoreShop\Component\Payment\PaymentTransitions;
use CoreShop\Component\Payment\Repository\PaymentRepositoryInterface;
use Payum\Core\Extension\Context;
use Payum\Core\Extension\ExtensionInterface;
use Payum\Core\Model\ModelAggregateInterface;
use Payum\Core\Storage\IdentityInterface;

final class UpdatePaymentStateExtension implements ExtensionInterface
{
    private array $scheduledPaymentsToProcess = [];

    public function __construct(
        private StateMachineManagerInterface $stateMachineManager,
        private PaymentRepositoryInterface $paymentRepository,
        private GetStatusFactoryInterface $getStatusRequestFactory
    ) {
    }

    public function onPreExecute(Context $context): void
    {
        /** @var mixed|ModelAggregateInterface $request */
        $request = $context->getRequest();

        if (false === $request instanceof ModelAggregateInterface) {
            return;
        }

        if ($request->getModel() instanceof IdentityInterface) {
            $payment = $this->paymentRepository->find($request->getModel()->getId());
        } else {
            /** @var PaymentInterface|mixed $payment */
            $payment = $request->getModel();
        }

        if (false === $payment instanceof PaymentInterface) {
            return;
        }

        $this->scheduleForProcessingIfSupported($payment);
    }

    public function onExecute(Context $context): void
    {
        // do nothing
    }

    public function onPostExecute(Context $context): void
    {
        if (null !== $context->getException()) {
            return;
        }

        /** @var mixed|ModelAggregateInterface $request */
        $request = $context->getRequest();

        if ($request instanceof ModelAggregateInterface) {
            /** @var PaymentInterface|mixed $payment */
            $payment = $request->getModel();
            if ($payment instanceof PaymentInterface) {
                $this->scheduleForProcessingIfSupported($payment);
            }
        }

        if (count($context->getPrevious()) > 0) {
            return;
        }

        // Process scheduled payments only
        // when we are post executing a root payum request
        foreach ($this->scheduledPaymentsToProcess as $id => $payment) {
            $this->processPayment($context, $payment);
            unset($this->scheduledPaymentsToProcess[$id]);
        }
    }

    private function processPayment(Context $context, PaymentInterface $payment): void
    {
        $status = $this->getStatusRequestFactory->createNewWithModel($payment);
        $context->getGateway()->execute($status);

        /** @var string $value */
        $value = $status->getValue();
        if ($payment->getState() === $value) {
            return;
        }

        if (PaymentInterface::STATE_UNKNOWN === $value) {
            return;
        }

        $this->updatePaymentState($payment, $value);
    }

    private function scheduleForProcessingIfSupported(PaymentInterface $payment): void
    {
        $id = $payment->getId();
        if (null === $id) {
            return;
        }

        if (false === is_int($id)) {
            return;
        }

        $this->scheduledPaymentsToProcess[$id] = $payment;
    }

    private function updatePaymentState(PaymentInterface $payment, string $nextState): void
    {
        $workflow = $this->stateMachineManager->get($payment, PaymentTransitions::IDENTIFIER);
        if (null !== $transition = $this->stateMachineManager->getTransitionToState($workflow, $payment, $nextState)) {
            $workflow->apply($payment, $transition);
        }
    }
}
