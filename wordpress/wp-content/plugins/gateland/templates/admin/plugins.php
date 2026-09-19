<?php

use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Helper;
use Nabik\Gateland\Models\Transaction;

defined( 'ABSPATH' ) || exit;

wp_enqueue_style('custom-style', GATELAND_URL . 'assets/css/style.css', [], GATELAND_VERSION);
wp_enqueue_style('notyf-style', GATELAND_URL . 'assets/css/notyf.min.css', [], GATELAND_VERSION);

wp_enqueue_script( 'alpine' );
wp_enqueue_script('notyf-script', GATELAND_URL . 'assets/js/notyf.min.js', [], GATELAND_VERSION, true);
wp_enqueue_script('global-script', GATELAND_URL . 'assets/js/global.js', ['notyf-script'], GATELAND_VERSION, true);
wp_enqueue_script('page-script', GATELAND_URL . 'assets/js/pages/plugins.js', [], GATELAND_VERSION, true);

wp_localize_script( 'global-script', 'gateland', [
    'root'  => esc_url_raw( rest_url() ),
    'nonce' => wp_create_nonce( 'wp_rest' ),
] );
?>

<section x-data="gatelandPlugins" class="gateland-container">

    <section class="bg-[#F9FAFB] text-base text-gray-900 py-6 md:pl-5 pl-2.5">

        <div class="container">

            <div class="mb-8">
                <div class="mb-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="font-semibold text-lg">
                            افزونه‌های پشتیبانی شده توسط گیت‌لند
                        </div>
                    </div>
                </div>

                <!-- skeleton -->
                <div
                    x-show="pageLoaderIsActive"
                    class="grid grid-cols-12 gap-4"
                >
                    <template x-for="item in [1,2,3,4]">
                        <div class="lg:col-span-6 col-span-full">
                            <div class="h-full flex flex-col rounded-[8px] border border-gray-200 md:p-5 p-4">
                                <div class="flex items-start gap-3 mb-5">
                                    <div class="min-w-20 size-20 skeleton rounded"></div>
                                    <div class="w-full">
                                        <div class="md:flex items-center gap-3 mb-3">
                                            <div class="w-32 h-6 skeleton rounded-full"></div>
                                        </div>
                                        <div class="h-[40px] w-full">
                                            <div class="w-full h-3.5 skeleton rounded-full mb-1"></div>
                                            <div class="w-full h-3.5 skeleton rounded-full"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 mt-auto">
                                    <div class="w-36 h-9 skeleton rounded"></div>
                                    <div class="sm:block hidden w-28 h-9 skeleton rounded"></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div
                    x-show="!pageLoaderIsActive"
                    class="grid grid-cols-12 gap-4"
                >
                    <template x-for="plugin in plugins">
                        <div class="lg:col-span-6 col-span-full">
                            <div class="h-full flex flex-col rounded-[8px] border border-gray-200 md:p-5 p-4">
                                <div class="flex items-start gap-3 mb-5">
                                    <div class="min-w-20 w-20">
                                        <img class="w-full max-h-full overflow-hidden" :src="plugin.icon_url">
                                    </div>
                                    <div>
                                        <div class="md:flex items-center gap-3 mb-3">
                                            <div class="text-gray-900 text-base line-clamp-1 md:max-w-[50%]">
                                               <span x-text="plugin.title"></span>
                                            </div>
                                            <div x-show="plugin.author" class="text-sm line-clamp-1 md:max-w-[50%]">
                                                <span class="text-gray-500 ml-1">توسط</span>
                                                <template x-if="!plugin.author_url">
                                                    <span x-text="plugin.author"></span>
                                                </template>
                                                <template x-if="plugin.author_url">
                                                    <a :href="plugin.author_url" target="_blank" class="text-primary-500">
                                                        <span x-text="plugin.author"></span>
                                                    </a>
                                                </template>
                                            </div>
                                        </div>
                                        <div class="h-[40px] text-sm text-gray-800 line-clamp-2">
                                            <span x-text="plugin.description"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 mt-auto">

                                    <template x-if="plugin.coming_soon">
                                        <div class="flex items-center gap-1 text-indigo-700 bg-indigo-50 rounded-full text-sm py-1 px-3">
                                            <div class="min-w-3 w-3">
                                                <img class="max-w-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/stars.svg">
                                            </div>

                                            <span>به زودی...</span>
                                        </div>
                                    </template>

                                    <template x-if="!plugin.coming_soon & !plugin.is_installed & !plugin.is_activated">
                                        <a :href="plugin.install_url" target="_blank" class="flex items-center gap-1.5 text-sm text-primary-700 font-semibold rounded-[8px] bg-primary-50 hover:bg-primary-100 py-2 px-4">
                                            <div class="min-w-5 w-5">
                                                <img class="max-w-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/info-square-dark.svg">
                                            </div>
                                            <span>مشاهده و نصب</span>
                                        </a>
                                    </template>

                                    <template x-if="!plugin.coming_soon & plugin.is_installed & !plugin.is_activated">
                                        <a :href="plugin.activate_url" target="_blank" class="flex items-center gap-2 text-sm text-gray-700 font-semibold rounded-[8px] border border-gray-300 hover:bg-gray-100 py-2 px-4">
                                            <div class="min-w-5">
                                                <img class="max-w-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/power.svg">
                                            </div>
                                            <span>فعال سازی</span>
                                        </a>
                                    </template>

                                    <template x-if="!plugin.coming_soon & plugin.is_installed & !plugin.is_activated">
                                        <div class="flex items-center gap-1 text-success-700 bg-success-50 rounded-full text-sm py-1 px-3">
                                            <div class="min-w-3 w-3">
                                                <img class="max-w-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/check-circle-broken.svg">
                                            </div>
                                            <span>نصب شده</span>
                                        </div>
                                    </template>

                                    <template x-if="!plugin.coming_soon & plugin.is_installed & plugin.is_activated">
                                        <div class="flex items-center gap-1 text-success-700 bg-success-50 rounded-full text-sm py-1 px-3">
                                            <div class="min-w-3 w-3">
                                                <img class="max-w-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/check-circle-broken.svg">
                                            </div>
                                            <span>نصب و فعال </span>
                                        </div>
                                    </template>

                                    <template x-if="plugin.document_url">
                                        <a :href="plugin.document_url" target="_blank" class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-600 font-semibold rounded-[8px] border border-transparent hover:bg-gray-200 py-2 px-4">
                                            <div class="min-w-5">
                                                <img class="max-w-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/play-square.svg">
                                            </div>
                                            <span>آموزش</span>
                                        </a>
                                    </template>

                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div x-show="!pageLoaderIsActive" class="flex items-center flex-wrap gap-4 border border-[#738DBF] rounded-xl p-3 mb-5">
                <div class="flex items-start gap-4">
                    <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/featured-icon.svg">
                    <div>
                        <div class="text-gray-700 mb-1.5">
                            افزودن افزونه جدید به گیت‌لند
                        </div>
                        <div class="text-gray-600 font-normal">
                            در صورتی که از افزونه دیگری استفاده می‌کنید که نیاز دارید به گیت لند متصل شود، اطلاعات آن را برای ما ارسال کنید.
                        </div>
                    </div>
                </div>
                <a href="https://t.me/nabik_net" target="_blank" class="min-w-fit bg-primary-500 hover:bg-primary-600 flex items-center sm:gap-2 gap-1 text-sm text-white hover:!text-white rounded-[8px] text-nowrap py-2 px-3.5 mr-auto">
                    <img class="" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/plus.svg">
                    <span>ارسال رایگان درخواست</span>
                </a>
            </div>

        </div>

    </section>

</section>