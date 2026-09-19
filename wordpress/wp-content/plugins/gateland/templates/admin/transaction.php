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
wp_enqueue_script('page-script', GATELAND_URL . 'assets/js/pages/transaction.js', [], GATELAND_VERSION, true);

wp_localize_script('global-script', 'gateland', [
    'root' => esc_url_raw(rest_url()),
    'nonce' => wp_create_nonce('wp_rest'),
]);

?>

<section x-data="gatelandTransaction" class="gateland-container">

    <section class="bg-[#F9FAFB] text-base text-gray-900 py-6 md:pl-5 pl-2.5">
        <div class="container">

            <div class="mb-6" >
                <div
                        class="mb-3"
                        :class="{'opacity-0' : $store.page.printing}"
                >
                    <a href="?page=gateland-transactions" class="inline-flex items-center gap-2 hover:translate-x-0.5">
                        <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/arrow-back.svg">
                        <div class="text-sm text-primary-500 font-semibold">
                            بازگشت به لیست تراکنش‌ها
                        </div>
                    </a>
                </div>
                <div
                        class="flex flex-wrap gap-2"
                        :class="{'opacity-0' : $store.page.printing}"
                >
                    <div class="font-semibold text-lg">
                        جزئیات تراکنش
                        <span x-show="transaction" x-text="transaction?.id"></span>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mr-auto">
                        <button
                            x-show="transaction"
                            x-cloak
                            @click="$store.page.printTransaction()"
                            class="flex items-center text-nowrap gap-2 text-sm text-primary-700 font-semibold rounded-md hover:bg-primary-100 py-2 px-3"
                        >
                            <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/print.svg">
                            <span>
                                   پرینت تراکنش
                            </span>
                        </button>
                        <button
                            x-show="transaction"
                            x-cloak
                            @click="openRefundModal()"
                            class="flex items-center text-nowrap gap-2 text-sm text-primary-700 font-semibold rounded-md hover:bg-primary-100 disabled:hover:!bg-transparent disabled:opacity-60 py-2 px-3"
                            :disabled="!transaction?.can_refund"
                        >
                            <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/card-send-blue.svg">
                            <span>
                               استرداد تراکنش
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <div
                    id="section-to-print"
                    :class="{'absolute w-screen h-screen top-0 left-0' : $store.page.printing}"
            >
                <div>
                    <!-- skeleton -->
                    <template x-if-="pageLoaderIsActive">
                        <div class="grid grid-cols-12 md:gap-4 gap-y-4 bg-white border border-gray-200 rounded-xl py-4 px-6 mb-5">
                            <template x-for="item in [1,2,3,4]">
                                <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                                    <div class="skeleton w-16 h-4 rounded-full mb-1.5"></div>
                                    <div class="skeleton w-20 h-5 rounded-full"></div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="!pageLoaderIsActive">
                        <div class="grid grid-cols-12 md:gap-4 gap-y-4 bg-white border border-gray-200 rounded-xl py-4 px-6 mb-5">
                            <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                                <div class="text-primary-500 text-xs mb-1.5">شماره سفارش: </div>
                                <a
                                        :href="transaction?.order.url"
                                        target="_blank"
                                        class="inline-flex items-center gap-2"
                                >
                                    <div class="text-primary-700 text-sm font-medium">
                                        <span x-text="transaction?.order.id"></span>
                                    </div>
                                    <img class="h-4" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/link-external.svg">
                                </a>
                            </div>
                            <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                                <div class="text-primary-500 text-xs mb-1.5">مبلغ:</div>
                                <div class="text-primary-700 text-sm">
                                    <span x-text="gatelandFormatPrice(transaction?.amount)"></span>
                                    <span x-text="transaction?.currency"></span>
                                </div>
                            </div>
                            <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                                <div class="text-primary-500 text-xs mb-1.5">وضعیت:</div>
                                <template x-if="transaction?.status === 'paid'">
                                    <div class="inline-block rounded-full bg-success-50 text-xs text-nowrap text-success-700 px-2 py-1">
                                        پرداخت شده
                                    </div>
                                </template>
                                <template x-if="transaction?.status === 'failed'">
                                    <div class="inline-block rounded-full bg-error-50 text-xs text-nowrap text-error-700 px-2 py-1">
                                        ناموفق
                                    </div>
                                </template>
                                <template x-if="transaction?.status === 'pending'">
                                    <div class="inline-block rounded-full bg-warning-50 text-xs text-nowrap text-warning-700 px-2 py-1">
                                        در انتظار پرداخت
                                    </div>
                                </template>
                                <template x-if="transaction?.status === 'refund'">
                                    <div class="inline-block rounded-full bg-gray-100 text-xs text-nowrap text-gray-700 px-2 py-1">
                                        استرداد شده
                                    </div>
                                </template>
                            </div>
                            <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                                <div class="text-primary-500 text-xs mb-1.5">درگاه: </div>
                                <div class="text-primary-700 text-sm">
                                    <span x-text="transaction?.gateway"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- levels-->
                <div>
                    <!-- skeleton -->
                    <template x-if="pageLoaderIsActive">
                        <div class="bg-white border border-gray-100 rounded-2xl sm:py-8 py-3 md:px-8 px-3 mb-5">
                            <div class="sm:flex justify-center relative">

                                <!--line-->
                                <div class="sm:hidden h-full w-[2px] absolute top-0 right-4 bg-gray-200"></div>

                                <div class="sm:w-[180px] sm:block flex gap-1 sm:mb-0 mb-4">
                                    <div class="relative z-10 flex justify-center sm:mb-3">
                                        <!-- circle -->
                                        <div class="size-8 rounded-full">
                                            <div class="skeleton size-8 bg-primary-100 flex items-center justify-center rounded-full">
                                            </div>
                                        </div>

                                        <!--line-->
                                        <div class="sm:block hidden w-1/2 absolute -z-10 top-[50%] -mt-[1px] left-0 h-[2px] bg-gray-200"></div>
                                    </div>
                                    <div class="sm:w-auto w-[calc(100%-36px)] sm:block flex flex-wrap items-center px-0.5">
                                        <div class="skeleton sm:w-20 w-16 h-5 rounded-full sm:mb-1 sm:mx-auto"></div>
                                        <div class="skeleton sm:w-28 w-20 h-4 rounded-full sm:mb-2 sm:mx-auto mr-auto"></div>
                                    </div>
                                </div>

                                <div class="sm:w-[180px] sm:block flex gap-1 sm:mb-0 mb-4">
                                    <div class="relative z-10 flex justify-center sm:mb-4">
                                        <!-- circle -->
                                        <div class="size-8 rounded-full">
                                            <div class="skeleton size-8 bg-primary-100 flex items-center justify-center rounded-full">
                                            </div>
                                        </div>

                                        <!--line-->
                                        <div class="sm:block hidden w-1/2 absolute -z-10 top-[50%] -mt-[1px] right-0 h-[2px] bg-gray-200"></div>
                                        <div class="sm:block hidden w-1/2 absolute -z-10 top-[50%] -mt-[1px] left-0 h-[2px] bg-gray-200"></div>
                                    </div>
                                    <div class="sm:w-auto w-[calc(100%-36px)] sm:block flex flex-wrap items-center px-0.5">
                                        <div class="skeleton sm:w-20 w-16 h-5 rounded-full sm:mb-1 sm:mx-auto"></div>
                                        <div class="skeleton sm:w-28 w-20 h-4 rounded-full sm:mb-2 sm:mx-auto mr-auto"></div>
                                    </div>
                                </div>

                                <div class="sm:w-[180px] sm:block flex gap-1 sm:mb-0">
                                    <div class="relative z-10 flex justify-center sm:mb-4">
                                        <!-- circle -->
                                        <div class="size-8 rounded-full">
                                            <div class="skeleton size-8 bg-primary-100 flex items-center justify-center rounded-full">
                                            </div>
                                        </div>

                                        <!--line-->
                                        <div class="sm:block hidden w-1/2 absolute -z-10 top-[50%] -mt-[1px] right-0 h-[2px] bg-gray-200"></div>
                                    </div>
                                    <div class="sm:w-auto w-[calc(100%-36px)] sm:block flex flex-wrap items-center px-0.5">
                                        <div class="skeleton sm:w-20 w-16 h-5 rounded-full sm:mb-1 sm:mx-auto"></div>
                                        <div class="skeleton sm:w-28 w-20 h-4 rounded-full sm:mb-2 sm:mx-auto mr-auto"></div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </template>

                    <template x-if="!pageLoaderIsActive && transaction">
                        <div class="bg-white border border-gray-100 rounded-2xl sm:py-8 py-3 md:px-8 px-3 mb-5">
                            <div class="sm:flex justify-center relative">

                                <!--line-->
                                <div class="sm:hidden h-full w-[2px] absolute top-0 right-4 bg-gray-200"></div>

                                <div class="sm:w-[200px] text-center sm:block flex gap-1 sm:mb-0">
                                    <div class="relative z-10 flex justify-center sm:mb-4">
                                        <!-- circle -->
                                        <div class="size-8 min-w-8 rounded-full">
                                            <img class="size-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/prev-step.svg">
                                        </div>

                                        <!--line-->
                                        <div class="sm:block hidden w-1/2 absolute -z-10 top-[50%] -mt-[1px] left-0 h-[2px] bg-gray-200"></div>
                                    </div>
                                    <div class="sm:w-auto w-[calc(100%-36px)] text-center sm:block flex flex-wrap items-center px-0.5">
                                        <div class="text-sm font-semibold text-primary-700 sm:mb-1">
                                            تاریخ ایجاد
                                        </div>
                                        <div class="text-xs font-normal text-primary-600 sm:mb-2 sm:mr-0 mr-auto">
                                            <span x-text="transaction.created_at"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="sm:w-[200px] text-center sm:block flex flex-wrap gap-1 sm:mt-0 mt-4">
                                    <div class="relative z-10 flex justify-center sm:mb-4">
                                        <!-- circle -->
                                        <div class="size-8 rounded-full">
                                            <template x-if="transaction.paid_at">
                                                <div class="size-8 bg-primary-100 flex items-center justify-center rounded-full">
                                                    <img class="size-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/prev-step.svg">
                                                </div>
                                            </template>
                                            <template x-if="!transaction.paid_at">
                                                <div class="size-8 bg-warning-100 flex items-center justify-center rounded-full shadow-[0_1px_2px_0_#1018280F]">
                                                    <img class="size-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/current-step.svg">
                                                </div>
                                            </template>
                                        </div>

                                        <!--line-->
                                        <div class="sm:block hidden w-1/2 absolute -z-10 top-[50%] -mt-[1px] right-0 h-[2px] bg-gray-200"></div>
                                        <div class="sm:block hidden w-1/2 absolute -z-10 top-[50%] -mt-[1px] left-0 h-[2px] bg-gray-200"></div>
                                    </div>
                                    <div class="sm:w-auto w-[calc(100%-36px)] text-center sm:block flex items-center px-0.5">
                                        <div class="text-sm font-semibold text-primary-700 sm:mb-1">
                                            تاریخ پرداخت
                                        </div>
                                        <div class="text-xs font-normal text-primary-600 sm:mb-2 sm:mr-0 mr-auto">
                                            <span x-text="transaction.paid_at || 'نامشخص'"></span>
                                        </div>
                                    </div>

                                    <template x-if="!transaction.paid_at && transaction?.gateway_features.includes('InquiryFeature')">
                                        <button
                                                @click="inquiryTransaction(transaction.id)"
                                                :disabled="inquiryLoaderIsActive"
                                                class="sm:inline-flex hidden items-center gap-2 justify-center bg-primary-50 hover:bg-primary-100 rounded-[8px] py-2 md:px-3.5 px-2"
                                        >
                                            <span  class="sm:size-5 size-4" :class="{'rotation-animation' : inquiryLoaderIsActive}">
                                                <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/refresh.svg">
                                            </span>
                                            <span class="font-semibold text-primary-700 md:text-sm text-xs md:leading-5">استعلام تایید تراکنش </span>
                                        </button>
                                    </template>
                                </div>

                                <template x-if="!transaction.paid_at">
                                    <div class="sm:w-[200px] text-center sm:block flex flex-wrap gap-1 sm:mt-0 mt-4">
                                        <div class="relative z-10 flex justify-center sm:mb-4">
                                            <!-- circle -->
                                            <div class="size-8 bg-gray-100 flex items-center justify-center rounded-full">
                                                <img class="size-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/next-step.svg">
                                            </div>

                                            <!--line-->
                                            <div class="sm:block hidden w-1/2 absolute -z-10 top-[50%] -mt-[1px] right-0 h-[2px] bg-gray-200"></div>
                                        </div>
                                        <div class="sm:w-auto w-[calc(100%-36px)] text-center sm:block flex items-center px-0.5">
                                            <div class="text-sm font-semibold text-primary-700 sm:mb-1">
                                                تاریخ تایید
                                            </div>
                                            <div class="sm:hidden text-xs font-normal text-primary-600 sm:mb-2 sm:mr-0 mr-auto">
                                                <span x-text="transaction.paid_at || 'نامشخص'"></span>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="transaction.paid_at">
                                    <div class="sm:w-[200px] text-center sm:block flex flex-wrap gap-1 sm:mt-0 mt-4">
                                        <div class="relative z-10 flex justify-center sm:mb-4">
                                            <!-- circle -->
                                            <div class="size-8 rounded-full">
                                                <template x-if="transaction.verified_at">
                                                    <div class="size-8 bg-primary-100 flex items-center justify-center rounded-full">
                                                        <img class="size-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/prev-step.svg">
                                                    </div>
                                                </template>
                                                <template x-if="!transaction.verified_at">
                                                    <div class="size-8 bg-warning-100 flex items-center justify-center rounded-full shadow-[0_1px_2px_0_#1018280F]">
                                                        <img class="size-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/current-step.svg">
                                                    </div>
                                                </template>
                                            </div>

                                            <!--line-->
                                            <div class="sm:block hidden w-1/2 absolute -z-10 top-[50%] -mt-[1px] right-0 h-[2px] bg-gray-200"></div>
                                            <template x-if="transaction.refunded_at">
                                                <div class="sm:block hidden w-1/2 absolute -z-10 top-[50%] -mt-[1px] left-0 h-[2px] bg-gray-200"></div>
                                            </template>
                                        </div>
                                        <div class="sm:w-auto w-[calc(100%-36px)] text-center sm:block flex items-center px-0.5">
                                            <div class="text-sm font-semibold text-primary-700 sm:mb-1">
                                                تاریخ تایید
                                            </div>
                                            <div class="text-xs font-normal text-primary-600 sm:mb-2 sm:mb-2 sm:mr-0 mr-auto">
                                                <span x-text="transaction.verified_at || 'تایید نشده'"></span>
                                            </div>
                                            <template x-if="!transaction.verified_at && transaction?.gateway_features.includes('InquiryFeature')">
                                                <button
                                                        @click="inquiryTransaction(transaction.id)"
                                                        :disabled="inquiryLoaderIsActive"
                                                        class="sm:inline-flex items-center gap-2 bg-primary-50 hover:bg-primary-100 rounded-[8px] py-2 md:px-3.5 px-2"
                                                >
                                                <span  class="size-5 md:block hidden" :class="{'rotation-animation' : inquiryLoaderIsActive}">
                                                    <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/refresh.svg">
                                                </span>
                                                    <span class="font-semibold text-primary-700 md:text-sm md:leading-5 text-[10px] leading-3">استعلام تایید تراکنش </span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="transaction.refunded_at">
                                    <div class="sm:w-[200px] text-center sm:block flex gap-1 sm:mt-0 mt-4">
                                        <div class="relative z-10 flex justify-center sm:mb-4">
                                            <!-- circle -->
                                            <div class="size-8 min-w-8 rounded-full">
                                                <div class="size-8 bg-primary-100 flex items-center justify-center rounded-full">
                                                    <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/card-send-blue.svg">
                                                </div>
                                            </div>

                                            <!--line-->
                                            <div class="sm:block hidden w-1/2 absolute -z-10 top-[50%] -mt-[1px] right-0 h-[2px] bg-gray-200"></div>
                                        </div>
                                        <div class="sm:w-auto w-[calc(100%-36px)] text-center sm:block flex flex-wrap items-center px-0.5">
                                            <div class="text-sm font-semibold text-primary-700 sm:mb-1">
                                                تاریخ استرداد
                                            </div>
                                            <div class="text-xs font-normal text-primary-600 sm:mb-2 sm:mr-0 mr-auto">
                                                <span x-text="transaction.refunded_at"></span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <template x-if="!transaction.paid_at && transaction?.gateway_features.includes('InquiryFeature')">
                                <button
                                        @click="inquiryTransaction(transaction.id)"
                                        :disabled="inquiryLoaderIsActive"
                                        class="sm:hidden flex w-full items-center gap-2 justify-center bg-primary-50 hover:bg-primary-100 rounded-[8px] py-2 md:px-3.5 px-2 mt-2"
                                >
                                            <span  class="sm:size-5 size-4" :class="{'rotation-animation' : inquiryLoaderIsActive}">
                                                <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/refresh.svg">
                                            </span>
                                    <span class="font-semibold text-primary-700 md:text-sm text-xs md:leading-5">استعلام تایید تراکنش </span>
                                </button>
                            </template>

                            <template x-if="transaction.paid_at">
                                <template x-if="!transaction.verified_at && transaction?.gateway_features.includes('InquiryFeature')">
                                    <button
                                            @click="inquiryTransaction(transaction.id)"
                                            :disabled="inquiryLoaderIsActive"
                                            class="sm:hidden flex items-center gap-2 bg-primary-50 hover:bg-primary-100 rounded-[8px] py-2 md:px-3.5 px-2 mt-2"
                                    >
                                        <span  class="size-5 md:block hidden" :class="{'rotation-animation' : inquiryLoaderIsActive}">
                                            <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/refresh.svg">
                                        </span>
                                        <span class="font-semibold text-primary-700 md:text-sm md:leading-5 text-[10px] leading-3">استعلام تایید تراکنش </span>
                                    </button>
                                </template>
                            </template>
                        </div>
                    </template>
                </div>

                <!--table-->
                <template x-if="transaction?.refunded_at">
                    <div class="bg-white border border-gray-300 rounded-xl overflow-hidden mb-5">

                        <div class="flex items-center flex-wrap gap-3 p-4">
                            <div class="text-lg font-semibold order-first ml-auto">
                                جزئیات استرداد وجه تراکنش
                                <span x-show="transaction" x-text="transaction?.id"></span>
                            </div>
                        </div>

                        <div class="overflow-auto custom-scrollbar">
                            <table id="myTable" class="w-full">
                                <thead class="text-gray-600 text-nowrap text-xs font-semibold">
                                <tr class="border-y border-gray-200">
                                    <td class="bg-gray-100 py-3 px-5">
                                        عنوان
                                    </td>
                                    <td class="bg-gray-100 py-3 px-5">
                                        اطلاعات
                                    </td>
                                </tr>
                                </thead>
                                <tbody class="w-full text-sm text-gray-700">
                                <template x-if="pageLoaderIsActive">
                                    <template x-for="item in [1,2,3,4]">
                                        <tr class="border-b bg-white border-gray-200">
                                            <td class="py-4 md:px-5 px-3">
                                                <span class="inline-block skeleton w-20 h-5 rounded-full"></span>
                                            </td>
                                            <td class="py-4 md:px-5 px-3">
                                                <span class="inline-block skeleton w-28 h-5 rounded-full"></span>
                                            </td>
                                        </tr>
                                    </template>
                                </template>
                                <template x-if="!pageLoaderIsActive && transaction">
                                    <template x-for="[key, value] in Object.entries(transaction.refund_meta)">
                                        <tr class="border-b bg-white border-gray-200">
                                            <td class="py-4 md:px-5 px-3">
                                                <span x-text="key"></span>
                                            </td>
                                            <td class="py-4 md:px-5 px-3">
                                                <div x-text="value ? value : '-'" class="min-w-28"></div>
                                            </td>
                                        </tr>
                                    </template>
                                </template>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </template>

                <!--table-->
                <div class="bg-white border border-gray-300 rounded-xl overflow-hidden mb-5">

                    <div class="flex items-center flex-wrap gap-3 p-4">
                        <div class="text-lg font-semibold order-first ml-auto">
                            اطلاعات تراکنش
                            <span x-show="transaction" x-text="transaction?.id"></span>
                        </div>
                    </div>

                    <div class="overflow-auto custom-scrollbar">
                        <table id="myTable" class="w-full">
                            <thead class="text-gray-600 text-nowrap text-xs font-semibold">
                            <tr class="border-y border-gray-200">
                                <td class="bg-gray-100 py-3 px-5">
                                    عنوان
                                </td>
                                <td class="bg-gray-100 py-3 px-5">
                                    اطلاعات
                                </td>
                            </tr>
                            </thead>
                            <tbody class="w-full text-sm text-gray-700">
                            <template x-if="pageLoaderIsActive">
                                <template x-for="item in [1,2,3,4]">
                                    <tr class="border-b bg-white border-gray-200">
                                        <td class="py-4 md:px-5 px-3">
                                            <span class="inline-block skeleton w-20 h-5 rounded-full"></span>
                                        </td>
                                        <td class="py-4 md:px-5 px-3">
                                            <span class="inline-block skeleton w-28 h-5 rounded-full"></span>
                                        </td>
                                    </tr>
                                </template>
                            </template>
                            <template x-if="!pageLoaderIsActive && transaction">
                                <template x-for="[key, value] in Object.entries(transaction.meta)">
                                    <tr class="border-b bg-white border-gray-200">
                                        <td class="py-4 md:px-5 px-3">
                                            <span x-text="key"></span>
                                        </td>
                                        <td class="py-4 md:px-5 px-3">
                                            <div x-text="value ? value : '-'" class="min-w-28"></div>
                                        </td>
                                    </tr>
                                </template>
                            </template>
                            </tbody>
                        </table>
                    </div>

                </div>

                <div class="fixed w-screen h-screen top-0 left-0 -z-[9] bg-[#F9FAFB]"></div>

                <div class="absolute w-screen h-screen top-0 left-0 -z-10">
                    <div >

                        <template x-if="transaction?.refunded_at">
                            <div class="bg-white border border-gray-300 rounded-xl overflow-hidden mb-5">
                                <div class="flex items-center flex-wrap gap-3 p-4">
                                    <div class="text-lg font-semibold order-first ml-auto">
                                        جزئیات استرداد وجه تراکنش
                                        <span x-show="transaction" x-text="transaction?.id"></span>
                                    </div>
                                </div>

                                <div class="overflow-auto custom-scrollbar">
                                    <table id="myTable" class="w-full">
                                        <thead class="text-gray-600 text-nowrap text-xs font-semibold">
                                        <tr class="border-y border-gray-200">
                                            <td class="bg-gray-100 py-3 px-5">
                                                عنوان
                                            </td>
                                            <td class="bg-gray-100 py-3 px-5">
                                                اطلاعات
                                            </td>
                                        </tr>
                                        </thead>
                                        <tbody class="w-full text-sm text-gray-700">
                                        <template x-if="pageLoaderIsActive">
                                            <template x-for="item in [1,2,3,4]">
                                                <tr class="border-b bg-white border-gray-200">
                                                    <td class="py-4 md:px-5 px-3">
                                                        <span class="inline-block skeleton w-20 h-5 rounded-full"></span>
                                                    </td>
                                                    <td class="py-4 md:px-5 px-3">
                                                        <span class="inline-block skeleton w-28 h-5 rounded-full"></span>
                                                    </td>
                                                </tr>
                                            </template>
                                        </template>
                                        <template x-if="!pageLoaderIsActive && transaction">
                                            <template x-for="[key, value] in Object.entries(transaction.refund_meta)">
                                                <tr class="border-b bg-white border-gray-200">
                                                    <td class="py-4 md:px-5 px-3">
                                                        <span x-text="key"></span>
                                                    </td>
                                                    <td class="py-4 md:px-5 px-3">
                                                        <div x-text="value ? value : '-'" class="min-w-28"></div>
                                                    </td>
                                                </tr>
                                            </template>
                                        </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </template>

                        <div class="bg-white border border-gray-300 rounded-xl overflow-hidden mb-5">
                            <div class="flex items-center flex-wrap gap-3 p-4">
                                <div class="text-lg font-semibold order-first ml-auto">
                                    اطلاعات تراکنش
                                    <span x-show="transaction" x-text="transaction?.id"></span>
                                </div>
                            </div>

                            <div class="overflow-auto custom-scrollbar">
                                <table id="myTable" class="w-full">
                                    <thead class="text-gray-600 text-nowrap text-xs font-semibold">
                                    <tr class="border-y border-gray-200">
                                        <td class="bg-gray-100 py-3 px-5">
                                            عنوان
                                        </td>
                                        <td class="bg-gray-100 py-3 px-5">
                                            اطلاعات
                                        </td>
                                    </tr>
                                    </thead>
                                    <tbody class="w-full text-sm text-gray-700">
                                    <template x-if="pageLoaderIsActive">
                                        <template x-for="item in [1,2,3,4]">
                                            <tr class="border-b bg-white border-gray-200">
                                                <td class="py-4 md:px-5 px-3">
                                                    <span class="inline-block skeleton w-20 h-5 rounded-full"></span>
                                                </td>
                                                <td class="py-4 md:px-5 px-3">
                                                    <span class="inline-block skeleton w-28 h-5 rounded-full"></span>
                                                </td>
                                            </tr>
                                        </template>
                                    </template>
                                    <template x-if="!pageLoaderIsActive && transaction">
                                        <template x-for="[key, value] in Object.entries(transaction.meta)">
                                            <tr class="border-b bg-white border-gray-200">
                                                <td class="py-4 md:px-5 px-3">
                                                    <span x-text="key"></span>
                                                </td>
                                                <td class="py-4 md:px-5 px-3">
                                                    <div x-text="value ? value : '-'" class="min-w-28"></div>
                                                </td>
                                            </tr>
                                        </template>
                                    </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- refund -->
                <div
                        x-transition
                        x-cloak
                        class="fixed z-[99999] top-0 left-0 flex items-center justify-center w-full h-full overflow-auto custom-scrollbar py-10 px-4"
                        x-show="modals.refund.active"
                >
                    <!-- overlay -->
                    <div
                            @click="modals.refund.active = false"
                            class="fixed z-10 top-0 left-0 w-full h-full bg-black bg-opacity-50 cursor-pointer"
                    ></div>

                    <!-- modal body -->
                    <div class="bg-white text-gray-900 w-[480px] max-w-full z-20  rounded-xl p-5 my-auto">
                        <div class="mb-3">
                            <div class="size-12 flex items-center justify-center bg-error-50 rounded-full">
                                <div class="size-9 flex items-center justify-center bg-error-100 rounded-full">
                                    <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/card-send-red.svg">
                                </div>
                            </div>
                        </div>
                        <div class="font-semibold text-lg mb-1">
                            استرداد تراکنش
                        </div>
                        <div class="text-sm text-gray-600 font-light mb-6">
                            شما در حال استرداد تراکنش
                            <span x-text="modals.refund.transaction?.id"></span>
                            هستید.
                        </div>
                        <div class="flex items-center gap-1 bg-gray-100 rounded-xl md:text-sm text-xs text-center p-1 mb-6">
                            <button
                                    @click="modals.refund.data.type.value = 'auto'"
                                    class="w-1/2 cursor-pointer disabled:cursor-default rounded-xl text-gray-500 py-3"
                                    :class="{'bg-white shadow-[0_4px_20px_0_#9EA5B740] !text-gray-900' : (modals.refund.data.type.value === 'auto')}"
                                    :disabled="!modals.refund.transaction?.gateway_features.includes('RefundFeature')"
                            >
                                استرداد سیستمی
                            </button>
                            <button
                                    @click="modals.refund.data.type.value = 'manual'"
                                    class="w-1/2 text-gray-500 cursor-pointer rounded-xl py-3"
                                    :class="{'bg-white shadow-[0_4px_20px_0_#9EA5B740] !text-gray-900' : (modals.refund.data.type.value === 'manual')}"
                            >
                                استرداد دستی
                            </button>
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm mb-2">مبلغ استرداد </label>
                            <div class="relative">
                                <input
                                        x-model="modals.refund.data.amount.value"
                                        @input="modals.refund.data.amount.value = gatelandFormatPrice(modals.refund.data.amount.value)"
                                        class="w-full bg-white border !border-gray-300 shadow-[0_1px_2px_0_#1018280D] !rounded-lg !py-2 !px-3"
                                        placeholder="مبلغ را وارد کنید"
                                        type="text"
                                >
                                <div class="absolute left-0 top-0 h-full p-1">
                                    <div class="flex bg-white gap-1 h-full">
                                        <div class="flex items-center text-gray-600 px-2">
                                            تومان
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- help text -->
                            <div
                                    @click="modals.refund.data.amount.value = gatelandFormatPrice(modals.refund.transaction?.amount)"
                                    class="text-sm text-gray-600 cursor-pointer pt-1.5"
                            >
                                کل مبلغ تراکنش:
                                <span x-text="gatelandFormatPrice(modals.refund.transaction?.amount)"></span>
                                <span x-text="modals.refund.transaction?.currency"></span>
                            </div>
                            <!--error msg-->
                            <div
                                    x-text="modals.refund.data.amount.errorMsg"
                                    class="text-xs text-error-300 pt-1.5 empty:pt-0"
                            >
                            </div>
                        </div>
                        <div
                                x-transition
                                x-show="modals.refund.data.type.value === 'manual'"
                                class="mb-6"
                        >
                            <label class="block text-sm mb-2">شناسه استرداد</label>
                            <div>
                                <input
                                        x-model="modals.refund.data.refund_id.value"
                                        rows="5"
                                        class="w-full bg-white border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-lg resize-none py-2 px-3"
                                        placeholder="شماره استرداد را اینجا وارد کنید"
                                >
                            </div>
                            <!--error msg-->
                            <div
                                    x-text="modals.refund.data.refund_id.errorMsg"
                                    class="text-xs text-error-300 pt-1.5 empty:pt-0"
                            >
                            </div>
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm mb-2">توضیحات </label>
                            <div>
                            <textarea
                                    x-model="modals.refund.data.description.value"
                                    rows="5"
                                    class="w-full bg-white border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-lg resize-none py-2 px-3"
                                    placeholder="توضیحات استرداد را اینجا وارد کنید..."
                            ></textarea>
                            </div>
                            <!--error msg-->
                            <div
                                    x-text="modals.refund.data.description.errorMsg"
                                    class="text-xs text-error-300 pt-1.5 empty:pt-0"
                            >
                            </div>
                        </div>
                        <div class="flex sm:flex-nowrap flex-wrap justify-center gap-3">
                            <button
                                    @click="modals.refund.active = false"
                                    class="sm:w-1/2 w-full border border-gray-300 text-gray-700 font-semibold rounded-lg hover:shadow py-2"
                            >
                                انصراف
                            </button>
                            <button
                                    @click="refundTransaction(modals.refund.transaction.id)"
                                    class="flex justify-center items-center sm:w-1/2 w-full border bg-error-600 border-error-600 text-white font-semibold rounded-lg hover:shadow py-2"
                            >
                            <span x-show="!refundLoaderIsActive">
                                تایید و استرداد تراکنش
                            </span>
                                <span x-show="refundLoaderIsActive" class="rotation-animation size-6">
                                <img class="h-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/refresh-white.svg">
                            </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <div
            class="hidden absolute w-screen h-screen top-0 left-0 bg-white z-50"
            :class="{'!block' : $store.page.printing}"
    >
        <div class="h-full w-full flex items-center justify-center gap-2 text-primary-700 text-base">
            درحال آماده سازی پرینت
            <span  class="rotation-animation">
                <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/refresh.svg">
            </span>
        </div>
    </div>

</section>
