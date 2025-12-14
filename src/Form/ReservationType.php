<?php

namespace App\Form;

use App\Entity\Reservation;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('guest', EntityType::class, [
                'class' => User::class,
                'choice_label' => fn(User $u) => $u->getFullName() . ' (' . $u->getEmail() . ')',
                'placeholder' => 'Select guest',
            ])
            ->add('serviceType', ChoiceType::class, [
                'choices' => array_flip(Reservation::SERVICE_LABELS),
                'placeholder' => 'Select service type',
            ])
            ->add('serviceName', TextType::class, ['required' => false])
            ->add('serviceDetails', TextareaType::class, ['required' => false, 'attr' => ['rows' => 2]])
            ->add('checkInDate', DateType::class, ['widget' => 'single_text'])
            ->add('checkOutDate', DateType::class, ['widget' => 'single_text', 'required' => false])
            ->add('numberOfGuests', IntegerType::class, ['attr' => ['min' => 1]])
            ->add('totalAmount', NumberType::class, ['scale' => 2])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Pending' => Reservation::STATUS_PENDING,
                    'Confirmed' => Reservation::STATUS_CONFIRMED,
                    'Cancelled' => Reservation::STATUS_CANCELLED,
                    'Completed' => Reservation::STATUS_COMPLETED,
                    'No Show' => Reservation::STATUS_NO_SHOW,
                ],
            ])
            ->add('paymentStatus', ChoiceType::class, [
                'choices' => [
                    'Unpaid' => Reservation::PAYMENT_UNPAID,
                    'Partial' => Reservation::PAYMENT_PARTIAL,
                    'Paid' => Reservation::PAYMENT_PAID,
                    'Refunded' => Reservation::PAYMENT_REFUNDED,
                ],
            ])
            ->add('specialRequests', TextareaType::class, ['required' => false, 'attr' => ['rows' => 2]])
            ->add('adminNotes', TextareaType::class, ['required' => false, 'attr' => ['rows' => 2]])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Reservation::class]);
    }
}