<?php

namespace App\Form;

use App\Entity\Room;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class RoomType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('roomNumber', TextType::class, [
                'label' => 'Room Number',
                'attr' => [
                    'placeholder' => 'e.g., 101, 202, 305',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a room number']),
                ],
            ])
            ->add('roomType', ChoiceType::class, [
                'label' => 'Room Type',
                'choices' => [
                    'Standard Room' => 'Standard',
                    'Deluxe Room' => 'Deluxe',
                    'Suite' => 'Suite',
                    'Presidential Suite' => 'Presidential',
                    'Family Room' => 'Family',
                    'Single Room' => 'Single',
                    'Double Room' => 'Double',
                ],
                'placeholder' => 'Select room type',
                'constraints' => [
                    new NotBlank(['message' => 'Please select a room type']),
                ],
            ])
            ->add('pricePerNight', NumberType::class, [
                'label' => 'Price Per Night',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'step' => '0.01',
                ],
                'constraints' => [
                    new Positive(['message' => 'Price must be a positive number']),
                ],
            ])
            ->add('capacity', ChoiceType::class, [
                'label' => 'Guest Capacity',
                'choices' => [
                    '1 Guest' => 1,
                    '2 Guests' => 2,
                    '3 Guests' => 3,
                    '4 Guests' => 4,
                    '5 Guests' => 5,
                    '6 Guests' => 6,
                    '7 Guests' => 7,
                    '8 Guests' => 8,
                    '9 Guests' => 9,
                    '10 Guests' => 10,
                ],
                'placeholder' => 'Select capacity',
                'constraints' => [
                    new NotBlank(['message' => 'Please select guest capacity']),
                ],
            ])
            ->add('features', TextareaType::class, [
                'label' => 'Room Features',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Room Description',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Available' => 'Available',
                    'Reserved' => 'Reserved',
                    'Occupied' => 'Occupied',
                ],
                'placeholder' => 'Select status',
                'constraints' => [
                    new NotBlank(['message' => 'Please select a status']),
                ],
            ])
            ->add('mainImageFile', FileType::class, [
                'label' => 'Main Image',
                'mapped' => false,
                'required' => false,
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
            'data_class' => Room::class,
        ]);
    }
}