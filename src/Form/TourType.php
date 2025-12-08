<?php

namespace App\Form;

use App\Entity\Tour;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\All;

class TourType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Tour Name',
                'attr' => [
                    'placeholder' => 'Enter tour name',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a tour name'])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter tour description',
                    'class' => 'form-control',
                    'rows' => 5
                ]
            ])
            ->add('location', TextType::class, [
                'label' => 'Location',
                'attr' => [
                    'placeholder' => 'e.g., Boracay Island, Philippines',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a location'])
                ]
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Price per Person',
                'currency' => 'PHP',
                'attr' => [
                    'placeholder' => '0.00',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a price']),
                    new Positive(['message' => 'Price must be positive'])
                ]
            ])
            ->add('duration', ChoiceType::class, [
                'label' => 'Duration',
                'choices' => [
                    'Half Day' => 'Half Day',
                    'Full Day' => 'Full Day',
                    '1 Night 2 Days' => '1 Night 2 Days',
                    '2 Nights 3 Days' => '2 Nights 3 Days',
                    '3 Nights 4 Days' => '3 Nights 4 Days',
                    '4 Nights 5 Days' => '4 Nights 5 Days',
                    '5 Nights 6 Days' => '5 Nights 6 Days',
                    '6 Nights 7 Days' => '6 Nights 7 Days',
                ],
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please select a duration'])
                ]
            ])
            ->add('scheduleDate', DateTimeType::class, [
                'label' => 'Schedule Date & Time',
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('availableSlots', IntegerType::class, [
                'label' => 'Available Slots',
                'attr' => [
                    'placeholder' => 'Number of available slots',
                    'class' => 'form-control',
                    'min' => 0
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter available slots'])
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Available' => 'Available',
                    'Booked' => 'Booked',
                    'Cancelled' => 'Cancelled',
                    'Pending' => 'Pending',
                    'Completed' => 'Completed'
                ],
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('mainImageFile', FileType::class, [
                'label' => 'Main Image',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*'
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Please upload a valid image (JPEG, PNG or WebP)',
                    ]),
                ],
            ])
            ->add('galleryImageFiles', FileType::class, [
                'label' => 'Gallery Images',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*'
                ],
                'constraints' => [
                    new All([
                        'constraints' => [
                            new File([
                                'maxSize' => '5M',
                                'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                                'mimeTypesMessage' => 'Each file must be a valid image (JPEG, PNG or WebP)',
                            ]),
                        ],
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tour::class,
        ]);
    }
}