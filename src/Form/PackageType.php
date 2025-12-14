<?php

namespace App\Form;

use App\Entity\Package;
use App\Entity\Room;
use App\Entity\Tour;
use App\Entity\Food;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\All;

class PackageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Basic Info
            ->add('name', TextType::class, [
                'label' => 'Package Name',
                'attr' => ['placeholder' => 'e.g., Summer Getaway Special', 'class' => 'form-control'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 4, 'class' => 'form-control'],
            ])
            ->add('packagePrice', NumberType::class, [
                'label' => 'Package Price',
                'scale' => 2,
                'attr' => ['placeholder' => '0.00', 'class' => 'form-control', 'step' => '0.01'],
            ])
            ->add('packageType', ChoiceType::class, [
                'label' => 'Package Type',
                'choices' => [
                    'Weekend Getaway' => 'Weekend',
                    'Honeymoon' => 'Honeymoon',
                    'Family Package' => 'Family',
                    'Adventure' => 'Adventure',
                    'Business' => 'Business',
                    'Holiday Special' => 'Holiday',
                    'Romantic' => 'Romantic',
                    'Group' => 'Group',
                    'Custom' => 'Custom',
                ],
                'placeholder' => 'Select type...',
                'attr' => ['class' => 'form-control'],
            ])
            
            // Validity
            ->add('validFrom', DateType::class, [
                'label' => 'Valid From',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('validUntil', DateType::class, [
                'label' => 'Valid Until',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('maxRedemptions', IntegerType::class, [
                'label' => 'Max Redemptions',
                'required' => false,
                'attr' => ['placeholder' => 'Unlimited', 'min' => 1, 'class' => 'form-control'],
            ])
            
            // Duration
            ->add('durationDays', IntegerType::class, [
                'label' => 'Days',
                'required' => false,
                'attr' => ['min' => 1, 'class' => 'form-control'],
            ])
            ->add('durationNights', IntegerType::class, [
                'label' => 'Nights',
                'required' => false,
                'attr' => ['min' => 0, 'class' => 'form-control'],
            ])
            ->add('maxGuests', IntegerType::class, [
                'label' => 'Max Guests',
                'required' => false,
                'attr' => ['min' => 1, 'class' => 'form-control'],
            ])
            
            // Status
            ->add('isFeatured', CheckboxType::class, [
                'label' => 'Featured Package',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Active' => 'Active',
                    'Inactive' => 'Inactive',
                    'Draft' => 'Draft',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            
            // Terms
            ->add('inclusions', TextareaType::class, [
                'label' => 'Inclusions',
                'required' => false,
                'attr' => ['rows' => 3, 'class' => 'form-control'],
            ])
            ->add('exclusions', TextareaType::class, [
                'label' => 'Exclusions',
                'required' => false,
                'attr' => ['rows' => 3, 'class' => 'form-control'],
            ])
            ->add('termsAndConditions', TextareaType::class, [
                'label' => 'Terms & Conditions',
                'required' => false,
                'attr' => ['rows' => 3, 'class' => 'form-control'],
            ])
            
            // ============ ROOMS with choice_attr ============
            ->add('rooms', EntityType::class, [
                'class' => Room::class,
                'choice_label' => function (Room $room) {
                    return $room->getRoomType() . ' - Room #' . $room->getRoomNumber();
                },
                'choice_attr' => function (Room $room) {
                    return [
                        'data-price' => $room->getPricePerNight(),
                        'data-room-type' => $room->getRoomType(),
                        'data-room-number' => $room->getRoomNumber(),
                    ];
                },
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => 'Select Rooms',
            ])
            
            // ============ TOURS with choice_attr ============
            ->add('tours', EntityType::class, [
                'class' => Tour::class,
                'choice_label' => function (Tour $tour) {
                    return $tour->getName();
                },
                'choice_attr' => function (Tour $tour) {
                    return [
                        'data-price' => $tour->getPrice(),
                        'data-name' => $tour->getName(),
                        'data-duration' => method_exists($tour, 'getDuration') ? $tour->getDuration() : '',
                    ];
                },
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => 'Select Tours',
            ])
            
            // ============ FOODS with choice_attr ============
            ->add('foods', EntityType::class, [
                'class' => Food::class,
                'choice_label' => function (Food $food) {
                    return $food->getName();
                },
                'choice_attr' => function (Food $food) {
                    return [
                        'data-price' => $food->getPrice(),
                        'data-name' => $food->getName(),
                        'data-category' => method_exists($food, 'getCategory') ? $food->getCategory() : 'Food',
                    ];
                },
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => 'Select Food Items',
            ])
            
            // File Uploads (unmapped)
            ->add('mainImageFile', FileType::class, [
                'label' => 'Main Image',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Please upload a valid image (JPEG, PNG, WebP)',
                    ])
                ],
                'attr' => ['accept' => 'image/*', 'class' => 'form-control'],
            ])
            ->add('galleryImageFiles', FileType::class, [
                'label' => 'Gallery Images',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'constraints' => [
                    new All([
                        new File([
                            'maxSize' => '5M',
                            'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        ])
                    ])
                ],
                'attr' => ['accept' => 'image/*', 'multiple' => true, 'class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Package::class,
        ]);
    }
}