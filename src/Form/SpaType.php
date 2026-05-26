<?php

namespace App\Form;

use App\Entity\Spa;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class SpaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Service Name',
                'attr'  => ['placeholder' => 'e.g., Swedish Massage', 'class' => 'form-control'],
                'constraints' => [new NotBlank(['message' => 'Please enter a service name'])],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['placeholder' => 'Describe this spa or wellness service...', 'class' => 'form-control', 'rows' => 5],
            ])
            ->add('price', MoneyType::class, [
                'label'    => 'Price',
                'currency' => 'PHP',
                'attr'     => ['placeholder' => '0.00', 'class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a price']),
                    new Positive(['message' => 'Price must be positive']),
                ],
            ])
            ->add('duration', ChoiceType::class, [
                'label'   => 'Duration',
                'choices' => [
                    '30 minutes'  => '30 minutes',
                    '45 minutes'  => '45 minutes',
                    '60 minutes'  => '60 minutes',
                    '90 minutes'  => '90 minutes',
                    '120 minutes' => '120 minutes',
                    'Half Day'    => 'Half Day',
                    'Full Day'    => 'Full Day',
                ],
                'attr'        => ['class' => 'form-control'],
                'required'    => false,
                'placeholder' => 'Select duration',
            ])
            ->add('capacity', IntegerType::class, [
                'label'    => 'Capacity (persons)',
                'required' => false,
                'attr'     => ['placeholder' => 'e.g., 2', 'class' => 'form-control', 'min' => 1],
            ])
            ->add('category', ChoiceType::class, [
                'label'   => 'Category',
                'choices' => [
                    'Massage'    => 'Massage',
                    'Facial'     => 'Facial',
                    'Body Wrap'  => 'Body Wrap',
                    'Hydrotherapy' => 'Hydrotherapy',
                    'Wellness'   => 'Wellness',
                    'Couple'     => 'Couple',
                    'Other'      => 'Other',
                ],
                'required'    => false,
                'placeholder' => 'Select category',
                'attr'        => ['class' => 'form-control'],
            ])
            ->add('status', ChoiceType::class, [
                'label'   => 'Status',
                'choices' => [
                    'Available'   => 'Available',
                    'Unavailable' => 'Unavailable',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('isOffer', CheckboxType::class, [
                'label'    => 'Mark as Special Offer',
                'required' => false,
            ])
            ->add('mainImageFile', FileType::class, [
                'label'    => 'Main Image',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['class' => 'form-control', 'accept' => 'image/*'],
                'constraints' => [
                    new File([
                        'maxSize'             => '5M',
                        'mimeTypes'           => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage'    => 'Please upload a valid image (JPEG, PNG or WebP)',
                    ]),
                ],
            ])
            ->add('galleryImageFiles', FileType::class, [
                'label'    => 'Gallery Images',
                'mapped'   => false,
                'required' => false,
                'multiple' => true,
                'attr'     => ['class' => 'form-control', 'accept' => 'image/*'],
                'constraints' => [
                    new All([
                        'constraints' => [
                            new File([
                                'maxSize'          => '5M',
                                'mimeTypes'        => ['image/jpeg', 'image/png', 'image/webp'],
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
        $resolver->setDefaults(['data_class' => Spa::class]);
    }
}