<?php

use Nabik\Gateland\Gateways\BaseGateway;
use Nabik\Gateland\Gateways\Features\FreeFeature;
use Nabik\GatelandPro\GatelandPro;

defined('ABSPATH') || exit;

wp_enqueue_style('custom-style', GATELAND_URL . 'assets/css/style.css', [], GATELAND_VERSION);
wp_enqueue_style('notyf-style', GATELAND_URL . 'assets/css/notyf.min.css', [], GATELAND_VERSION);

wp_enqueue_script( 'alpine' );
wp_enqueue_script('draggable-script', GATELAND_URL . 'assets/js/draggable.js', [], GATELAND_VERSION, true);
wp_enqueue_script('notyf-script', GATELAND_URL . 'assets/js/notyf.min.js', [], GATELAND_VERSION, true);
wp_enqueue_script('global-script', GATELAND_URL . 'assets/js/global.js', ['notyf-script'], GATELAND_VERSION, true);
wp_enqueue_script('page-script', GATELAND_URL . 'assets/js/pages/gateways.js', [], GATELAND_VERSION, true);

wp_localize_script('global-script', 'gateland', [
    'root' => esc_url_raw(rest_url()),
    'nonce' => wp_create_nonce('wp_rest'),
]);
?>

<script>
    const assetsBaseUrl = "<?php echo GATELAND_URL . 'assets'; ?>"
</script>

<section x-data="gatelandGateways" id="sortGateways" class="gateland-container">

    <section class="bg-[#F9FAFB] text-base text-gray-900 py-6 md:pl-5 pl-2.5">

        <div class="container">

            <div class="flex items-center gap-2 flex-wrap mb-5">
                <div class="font-semibold text-lg flex items-center gap-2 ml-auto">
                    درگاه‌ها

                    <!-- skeleton -->
                    <template x-if="tableLoaderIsActive">
                        <div class="skeleton h-7 w-40 rounded-full"></div>
                    </template>

                    <template x-if="!tableLoaderIsActive">
                        <div class="flex items-center gap-0.5 bg-blue-50 text-blue-700 text-xs rounded-full py-1 px-3">
                            آی.پی شما:
                            <span x-text="host_ip"></span>
                            <button
                                @click="gatelandCopyToClipboard($el, 'آی.پی')"
                                :data-copy="host_ip"
                                class="mr-1"
                            >
                                <img class="" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/copy.svg">
                            </button>
                        </div>
                    </template>
                </div>
                <a href="?page=gateland-gateways-add" class="bg-primary-500 hover:bg-primary-600 flex items-center gap-2 text-sm text-white hover:text-white rounded-[8px] py-2 px-3.5">
                    <img class="" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/plus.svg">
                    <span>افزودن درگاه جدید</span>
                </a>
            </div>


            <!-- skeleton -->
            <template x-if="tableLoaderIsActive">
                <div class="border border-gray-300 rounded-xl overflow-hidden mb-5">
                    <div class="flex flex-wrap gap-2 items-center py-3 px-4">
                        <span class="font-semibold text-lg">
                            لیست درگاه‌ها
                        </span>
                    </div>

                    <!--table-->
                    <div>
                        <!--head-->
                        <div class="text-sm grid grid-cols-12 text-gray-600">
                            <div class="md:col-span-7 col-span-8">
                                <div class="bg-gray-100 py-3 px-5">
                                    نام درگاه
                                </div>
                            </div>
                            <div class="md:col-span-2 col-span-4">
                                <div class="bg-gray-100 md:text-right text-left py-3 px-5">
                                    وضعیت
                                </div>
                            </div>
                            <div class="md:block hidden md:col-span-3 col-span-full">
                                <div class="bg-gray-100 py-3 px-5">
                                    عملیات
                                </div>
                            </div>
                        </div>

                        <!--rows-->
                        <div class="gateway-rows">
                            <template x-for="row in (skeletonIds.length > 1 ? skeletonIds : [1])">
                                <div class="gateway-row text-sm grid grid-cols-12 bg-white border-b border-gray-200">
                                    <div class="md:col-span-7 col-span-8 flex items-center py-4 md:px-5 px-3">
                                        <div class="flex gap-3 items-center">
                                            <div class="skeleton size-5 min-w-5 rounded-md"></div>
                                            <div class="flex items-center gap-3 min-w-[250px]">
                                                <div class="skeleton size-10 min-w-10 rounded-md"></div>
                                                <div class="text-sm">
                                                    <div class="skeleton w-10 h-4 rounded-full mb-2"></div>
                                                    <div class="skeleton w-24 h-4 rounded-full"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="md:col-span-2 col-span-4 flex items-center py-4 md:px-5 px-3">
                                        <div class="skeleton w-16 h-4 rounded-full"></div>
                                    </div>
                                    <div class="md:col-span-3 col-span-full flex items-center md:justify-start justify-end py-4 md:px-5 px-3">
                                        <div class="flex items-center sm:gap-2 gap-1">
                                            <template x-for="row in [1,2,3]">
                                                <div class="skeleton size-7 rounded-md"></div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                </div>
            </template>

            <!-- empty -->
            <template x-if="!tableLoaderIsActive && tableData.length < 1">
                <div class="border border-gray-300 rounded-xl flex flex-col items-center justify-center text-center sm:p-10 p-5">
                    <div class="size-12 flex items-center justify-center bg-primary-50 rounded-full mb-3">
                        <div class="size-9 flex items-center justify-center bg-primary-100 rounded-full">
                            <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/plus-square-blue.svg">
                        </div>
                    </div>
                    <div class="font-semibold text-gray-900 mb-3">
                        هنوز هیچ درگاه فعالی ندارید!
                    </div>
                    <div class="max-w-[575px] text-gray-600 mb-6">
                        برای شروع تراکنش‌ها، باید یک درگاه پرداخت اضافه کنید. گیت‌لند از بیش از ۴۰
                        درگاه پرداخت پشتیبانی می‌کند تا تجربه‌ای سریع و ایمن برای شما و مشتریانتان فراهم شود.
                    </div>
                    <a href="?page=gateland-gateways-add" class="min-w-fit bg-primary-500 hover:bg-primary-600 flex items-center sm:gap-2 gap-1 text-sm text-white hover:text-white rounded-[8px] text-nowrap py-2 px-3.5">
                        <img  src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/plus.svg">
                        <span>افزودن اولین درگاه</span>
                    </a>
                </div>
            </template>

            <div x-show="!tableLoaderIsActive && tableData.length >= 1">
                <div class="border border-gray-300 rounded-xl overflow-hidden mb-5">
                    <div class="flex flex-wrap gap-2 items-center py-3 px-4">
                        <span class="font-semibold text-lg">
                            لیست درگاه‌ها
                        </span>
                    </div>

                    <!--table-->
                    <div>
                        <!--head-->
                        <div class="text-sm grid grid-cols-12 text-gray-600">
                            <div class="md:col-span-7 col-span-8">
                                <div class="bg-gray-100 py-3 px-5">
                                    نام درگاه
                                </div>
                            </div>
                            <div class="md:col-span-2 col-span-4">
                                <div class="bg-gray-100 md:text-right text-left py-3 px-5">
                                    وضعیت
                                </div>
                            </div>
                            <div class="md:block hidden md:col-span-3 col-span-full">
                                <div class="bg-gray-100 py-3 px-5">
                                    عملیات
                                </div>
                            </div>
                        </div>

                        <!--rows-->
                        <div class="gateway-rows">
                            <div id="gatewaysTable">
                                <!-- load data by js -->
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div x-show="tableData.length > 0" class="flex items-center flex-wrap gap-4 border border-[#738DBF] bg-primary-25 rounded-xl p-3 mb-5">
                <div class="flex items-start gap-4">
                    <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/featured-icon.svg">
                    <div>
                        <div class="text-gray-700 mb-1.5">
                            با افزودن درگاه‌های متفاوت، شانس موفقیت تراکنش‌هایتان را افزایش دهید.
                        </div>
                        <div class="text-gray-600 font-normal">
                            گیت‌لند از بیش از ۴۰ درگاه پرداخت پشتیبانی می‌کند تا بهترین تجربه پرداخت را برای شما و مشتریانتان تضمین کند.
                        </div>
                    </div>
                </div>
                <a href="?page=gateland-gateways-add" class="min-w-fit bg-primary-500 hover:bg-primary-600 flex items-center sm:gap-2 gap-1 text-sm text-white hover:text-white rounded-[8px] text-nowrap py-2 px-3.5 mr-auto">
                    <img class="" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/plus.svg">
                    <span>افزودن درگاه جدید</span>
                </a>
            </div>
        </div>

        <!-- delete modal -->
        <div
                x-transition
                x-cloak
                class="fixed top-0 left-0 z-10 flex items-center justify-center w-full h-full overflow-auto custom-scrollbar p-4"
                x-show="modals.delete.active"
        >
            <!-- overlay -->
            <div
                    @click="modals.delete.active = false"
                    class="fixed z-10 top-0 left-0 w-full h-full bg-black bg-opacity-50 cursor-pointer"
            ></div>

            <!-- body -->
            <div class="bg-white w-[480px] max-w-full z-20  rounded-xl p-5 my-auto">
                <div class="mb-3">
                    <div class="size-12 flex items-center justify-center bg-error-50 rounded-full">
                        <div class="size-9 flex items-center justify-center bg-error-100 rounded-full">
                            <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/trash-red.svg">
                        </div>
                    </div>
                </div>
                <div class="font-semibold text-lg mb-1">
                    حذف درگاه
                    <span x-text="modals.delete.gateway?.name"></span>
                </div>
                <div class="text-sm text-gray-600 font-light mb-6">
                    در حال حذف درگاه
                    <span x-text="modals.delete.gateway?.name" class="font-semibold"></span>
                    هستید.
                    <br>
                    آیا از این کار اطمینان دارید؟
                </div>
                <div class="mb-5 hidden">
                    <label class="block text-sm text-gray-700 mb-1">
                        نام درگاه را وارد کنید.
                    </label>
                    <input type="text" class="border text-sm  border-gray-300 text-gray-900 w-full rounded-lg !py-1.5 px-3.5" placeholder="شناسه را وارد کنید">
                    <div class="text-xs text-error-500 pt-1 empty:pt-0">
                        متن خطا!
                    </div>
                </div>
                <div class="flex items-center justify-center gap-3">
                    <button
                            @click="modals.delete.active = false"
                            class="w-1/2 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:shadow py-2"
                    >
                        انصراف
                    </button>
                    <button @click="deleteGateway()" class="w-1/2 border bg-error-600 border-error-600 text-white font-semibold rounded-lg hover:shadow  py-2">
                        حذف درگاه
                    </button>
                </div>
            </div>
        </div>

        <!-- status modal -->
        <div
                x-transition
                x-cloak
                class="fixed top-0 left-0 z-10 flex items-center justify-center w-full h-full overflow-auto custom-scrollbar p-4"
                x-show="modals.status.active"
        >
            <!-- overlay -->
            <div
                    @click="modals.status.active = false"
                    class="fixed z-10 top-0 left-0 w-full h-full bg-black bg-opacity-50 cursor-pointer"
            ></div>

            <!-- body -->
            <div class="bg-white w-[480px] max-w-full z-20  rounded-xl p-5 my-auto">
                <div class="mb-3">
                    <div class="size-12 flex items-center justify-center bg-primary-50 rounded-full">
                        <div class="size-9 flex items-center justify-center bg-primary-100 rounded-full">
                            <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/active.svg">
                        </div>
                    </div>
                </div>
                <div class="font-semibold text-lg mb-1">
                    <template x-if="modals.status.gateway?.status === 'active'">
                        <span>غیرفعال‌سازی </span>
                    </template>
                    <template x-if="modals.status.gateway?.status === 'inactive'">
                        <span>فعال‌سازی </span>
                    </template>
                    درگاه
                    <span x-text="modals.status.gateway?.name"></span>
                </div>
                <div class="text-sm text-gray-600 font-light mb-6">
                    شما در حال
                    <template x-if="modals.status.gateway?.status === 'active'">
                        <span>غیرفعال‌سازی </span>
                    </template>
                    <template x-if="modals.status.gateway?.status === 'inactive'">
                        <span>فعال‌سازی </span>
                    </template>
                    درگاه
                    <span x-text="modals.status.gateway?.name" class="font-semibold"></span>
                    هستید.
                    <br>
                    آیا از این کار اطمینان دارید؟
                </div>
                <div class="flex items-center justify-center gap-3">
                    <button
                            @click="modals.status.active = false"
                            class="w-1/2 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:shadow py-2"
                    >
                        انصراف
                    </button>
                    <button @click="changeStatusGateway()" class="w-1/2 border bg-primary-600 border-primary-600 text-white font-semibold rounded-lg hover:shadow  py-2">
                        تایید
                    </button>
                </div>
            </div>
        </div>

    </section>

</section>
