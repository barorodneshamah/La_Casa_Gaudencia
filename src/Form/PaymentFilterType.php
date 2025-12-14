<?php

namespace App\Form;

use App\Entity\Payment;
use App\Entity\Reservation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'required' => false,
                'placeholder' => 'All Status',
                'choices' => [
                    'Pending' => Payment::STATUS_PENDING,
                    'Approved' => Payment::STATUS_APPROVED,
                    'Rejected' => Payment::STATUS_REJECTED,
                    'Refunded' => Payment::STATUS_REFUNDED,
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Method',
                'required' => false,
                'placeholder' => 'All Methods',
                'choices' => [
                    'GCash' => Payment::METHOD_GCASH,
                    'Maya' => Payment::METHOD_MAYA,
                    'Bank Transfer' => Payment::METHOD_BANK_TRANSFER,
                    'Credit Card' => Payment::METHOD_CREDIT_CARD,
                    'Cash' => Payment::METHOD_CASH,
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('dateFrom', DateType::class, [
                'label' => 'From',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('dateTo', DateType::class, [
                'label' => 'To',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('search', TextType::class, [
                'label' => 'Search',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Transaction ID, guest name...'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}