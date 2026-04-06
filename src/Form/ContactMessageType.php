<?php

namespace App\Form;

use App\Entity\ContactMessage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ContactMessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Full Name',
                'attr' => ['placeholder' => 'Your full name'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please enter your name']),
                    new Assert\Length(['min' => 2, 'max' => 255])
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr' => ['placeholder' => 'your@email.com'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please enter your email']),
                    new Assert\Email(['message' => 'Please enter a valid email'])
                ]
            ])
            ->add('phone', TelType::class, [
                'label' => 'Phone Number',
                'required' => false,
                'attr' => ['placeholder' => '+63 XXX XXX XXXX']
            ])
            ->add('subject', ChoiceType::class, [
                'label' => 'Subject',
                'placeholder' => 'Select a topic',
                'choices' => [
                    'Booking Inquiry' => 'Booking Inquiry',
                    'Tour Information' => 'Tour Information',
                    'Dining Reservation' => 'Dining Reservation',
                    'General Question' => 'General Question',
                    'Feedback' => 'Feedback',
                    'Partnership' => 'Partnership',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please select a subject'])
                ]
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Your Message',
                'attr' => [
                    'placeholder' => 'Tell us how we can help you...',
                    'rows' => 6
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please enter your message']),
                    new Assert\Length(['min' => 10, 'max' => 5000])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactMessage::class,
        ]);
    }
}