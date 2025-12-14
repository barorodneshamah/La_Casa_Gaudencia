<?php

namespace App\Form;

use App\Entity\Payment;
use App\Entity\Reservation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class PaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Link to Reservation
            ->add('reservation', EntityType::class, [
                'class' => Reservation::class,
                'choice_label' => function (Reservation $reservation) {
                    return sprintf(
                        '%s - %s (%s) - ₱%s',
                        $reservation->getReservationNumber(),
                        $reservation->getGuest()->getFullName() ?? $reservation->getGuest()->getUsername(),
                        $reservation->getServiceTypeLabel(),
                        number_format((float) $reservation->getTotalAmount(), 2)
                    );
                },
                'placeholder' => 'Select a reservation',
                'label' => 'Reservation',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Please select a reservation']),
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])

            // Payment Amount
            ->add('amount', NumberType::class, [
                'label' => 'Payment Amount (₱)',
                'scale' => 2,
                'constraints' => [
                    new NotBlank(['message' => 'Please enter the amount']),
                    new Positive(['message' => 'Amount must be greater than 0']),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '0.00',
                    'step' => '0.01'
                ]
            ])

            // Payment Method
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Payment Method',
                'choices' => [
                    'Cash' => Payment::METHOD_CASH,
                    'GCash' => Payment::METHOD_GCASH,
                    'Maya' => Payment::METHOD_MAYA,
                    'Bank Transfer' => Payment::METHOD_BANK_TRANSFER,
                    'Credit Card' => Payment::METHOD_CREDIT_CARD,
                    'Debit Card' => Payment::METHOD_DEBIT_CARD,
                    'PayPal' => Payment::METHOD_PAYPAL,
                ],
                'placeholder' => 'Select payment method',
                'constraints' => [
                    new NotBlank(['message' => 'Please select a payment method']),
                ],
                'attr' => ['class' => 'form-select']
            ])

            // Payment Status (Admin can set this)
            ->add('status', ChoiceType::class, [
                'label' => 'Payment Status',
                'choices' => [
                    'Pending (Awaiting Approval)' => Payment::STATUS_PENDING,
                    'Approved' => Payment::STATUS_APPROVED,
                    'Rejected' => Payment::STATUS_REJECTED,
                    'Refunded' => Payment::STATUS_REFUNDED,
                    'Cancelled' => Payment::STATUS_CANCELLED,
                ],
                'attr' => ['class' => 'form-select']
            ])

            // Reference Number
            ->add('referenceNumber', TextType::class, [
                'label' => 'Reference / Transaction Number',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter reference number (if applicable)'
                ]
            ])

            // Proof of Payment (File Upload)
            ->add('proofOfPaymentFile', FileType::class, [
                'label' => 'Proof of Payment',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'application/pdf',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image or PDF',
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*,.pdf'
                ],
                'help' => 'Upload screenshot or receipt (Max 5MB)'
            ])

            // Guest Notes
            ->add('guestNotes', TextareaType::class, [
                'label' => 'Guest Notes',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 2,
                    'placeholder' => 'Notes from the guest...'
                ]
            ])

            // Admin Notes
            ->add('adminNotes', TextareaType::class, [
                'label' => 'Admin Notes',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 2,
                    'placeholder' => 'Internal notes (only visible to admin)...'
                ]
            ])

            // Rejection Reason (if rejected)
            ->add('rejectionReason', TextareaType::class, [
                'label' => 'Rejection Reason',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 2,
                    'placeholder' => 'Reason for rejection (if applicable)...'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Payment::class,
        ]);
    }
}