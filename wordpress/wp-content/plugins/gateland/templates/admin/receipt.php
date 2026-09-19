<?php

use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Helper;
use Nabik\Gateland\Models\Transaction;

defined( 'ABSPATH' ) || exit;

wp_enqueue_style('custom-style', GATELAND_URL . 'assets/css/style.css', [], GATELAND_VERSION);
wp_enqueue_style('notyf-style', GATELAND_URL . 'assets/css/notyf.min.css', [], GATELAND_VERSION);

wp_enqueue_script('notyf-script', GATELAND_URL . 'assets/js/notyf.min.js', [], GATELAND_VERSION, true);
wp_enqueue_script('global-script', GATELAND_URL . 'assets/js/global.js', ['notyf-script'], GATELAND_VERSION, true);
wp_enqueue_script('page-script', GATELAND_URL . 'assets/js/pages/receipt.js', [], GATELAND_VERSION, true);
wp_enqueue_script('alpine-script', GATELAND_URL . 'assets/js/alpine.min.js', ['global-script', 'page-script'], GATELAND_VERSION, ['strategy' => 'defer']);

wp_localize_script( 'global-script', 'gateland', [
	'root'  => esc_url_raw( rest_url() ),
	'nonce' => wp_create_nonce( 'wp_rest' ),
] );
?>

<section x-data="receipt" class="gateland-container">

    <section class="bg-[#F9FAFB] text-base text-gray-900 py-6 md:pl-5 pl-2.5">
        <div class="container">

            <div class="mb-6">
                <div class="mb-3">
                    <a href="?page=gateland-receipts" class="inline-flex items-center gap-2 hover:translate-x-0.5">
                        <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/arrow-back.svg">
                        <div class="text-sm text-primary-500 font-semibold">
                            بازگشت به لیست رسید‌های کارت به کارت
                        </div>
                    </a>
                </div>
                <div class="flex flex-wrap gap-2">
                    <div class="font-semibold text-lg">
                        جزئیات رسید
                        <span x-show="receipt" x-text="receipt?.id"></span>
                    </div>

                    <div
                            x-show="receipt && !pageLoaderIsActive"
                            x-cloak
                            class="flex flex-wrap items-center gap-x-2 gap-y-1 mr-auto"
                    >
                        <button
                                @click="openAcceptModal(true)"
                                x-show="receipt?.status === 'rejected'"
                                class="flex items-center text-nowrap gap-2 text-sm text-primary-700 font-semibold rounded-md hover:bg-primary-100 py-2 px-3"
                        >
                            <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/reverse-left.svg">
                            <span>
                                  تعیین وضعیت مجدد
                            </span>
                        </button>
                        <button
                                @click="$store.page.printTransaction()"
                                class="flex items-center text-nowrap gap-2 text-sm text-primary-700 font-semibold rounded-md hover:bg-primary-100 py-2 px-3"
                        >
                            <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/print.svg">
                            <span>
                                   پرینت رسید
                            </span>
                        </button>
                        <a
                                x-show="receipt?.pending_receipts_count"
                                :href="`?page=gateland-receipt&receipt_id=${receipt?.next_receipt_id}`"
                                class="flex items-center text-nowrap gap-2 text-sm text-primary-700 font-semibold rounded-md hover:bg-primary-100 disabled:hover:!bg-transparent disabled:opacity-60 py-2 px-3"
                        >
                            <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/arrow-square-left.svg">
                            <span>
                              رسید بعدی
                            </span>
                            <span x-text="receipt?.pending_receipts_count" class="min-w-6 h-6 rounded-full bg-blue-50 text-blue-700 py-1 px-2"></span>
                        </a>
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

                    <template x-if="!pageLoaderIsActive && receipt">
                        <div class="grid grid-cols-12 md:gap-4 gap-y-4 bg-white border border-gray-200 rounded-xl py-4 px-6 mb-5">
                            <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                                <div class="text-primary-500 text-xs mb-1.5">شماره تراکنش: </div>
                                <a
                                        :href="`?page=gateland-transaction&transaction_id=${receipt.transaction.id}`"
                                        target="_blank"
                                        class="inline-flex items-center gap-2"
                                >
                                    <div class="text-primary-700 text-sm font-medium">
                                        <span x-text="receipt.transaction.id"></span>
                                    </div>
                                    <img class="h-4" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/link-external.svg">
                                </a>
                            </div>
                            <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                                <div class="text-primary-500 text-xs mb-1.5">مبلغ تراکنش:</div>
                                <div class="text-primary-700 text-sm font-medium">
                                    <span x-text="gatelandFormatPrice(receipt.transaction.amount)"></span>
                                    <span x-text="receipt.amount> 0 ? receipt.currency : ''"></span>
                                </div>
                            </div>
                            <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                                <div class="text-primary-500 text-xs mb-1.5">وضعیت تراکنش:</div>
                                <template x-if="receipt.transaction.status === 'paid'">
                                    <div class="inline-block rounded-full bg-success-50 text-xs text-nowrap text-success-700 px-2 py-1">
                                        پرداخت شده
                                    </div>
                                </template>
                                <template x-if="receipt.transaction.status === 'failed'">
                                    <div class="inline-block rounded-full bg-error-50 text-xs text-nowrap text-error-700 px-2 py-1">
                                        ناموفق
                                    </div>
                                </template>
                                <template x-if="receipt.transaction.status === 'pending'">
                                    <div class="inline-block rounded-full bg-warning-50 text-xs text-nowrap text-warning-700 px-2 py-1">
                                        در انتظار پرداخت
                                    </div>
                                </template>
                                <template x-if="receipt.transaction.status === 'refund'">
                                    <div class="inline-block rounded-full bg-gray-100 text-xs text-nowrap text-gray-700 px-2 py-1">
                                        استرداد شده
                                    </div>
                                </template>
                            </div>
                            <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                                <div class="text-primary-500 text-xs mb-1.5">پذیرنده: </div>
                                <div class="text-primary-700 text-sm font-medium">
                                    <span x-text="receipt.transaction.client"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div>
                    <!-- skeleton -->
                    <template x-if-="pageLoaderIsActive">
                        <div class="grid grid-cols-12 md:gap-4 gap-y-4 bg-white border border-gray-200 rounded-xl py-4 px-6 mb-5">
                            <template x-for="item in [1,2]">
                                <div class="lg:col-span-6 col-span-full">
                                    <div class="flex items-center gap-2">
                                        <div class="skeleton size-11 min-w-11 rounded-lg"></div>
                                        <div>
                                            <div class="skeleton h-4 w-10 rounded-full mb-2"></div>
                                            <div class="skeleton h-4 w-36 rounded-full"></div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="!pageLoaderIsActive && receipt">
                        <div class="grid grid-cols-12 md:gap-4 gap-y-4 bg-white border border-gray-200 rounded-xl py-4 px-6 mb-5">
                            <div class="lg:col-span-6 col-span-full">
                                <div class="flex items-center gap-2">
                                    <div class="size-11 min-w-11 sm:flex hidden items-center justify-center bg-primary-50 rounded-lg">
                                        <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/credit-card-up.svg">
                                    </div>
                                    <div>
                                        <div class="text-primary-500 text-xs mb-1">
                                            کارت یا شبا مبدا (مشتری):
                                        </div>
                                        <div class="flex items-center gap-2 md:flex-nowrap flex-wrap">
                                            <div class="font-medium text-primary-700">
                                                <span x-text="receipt.source_card.card_number"></span>
                                            </div>
                                            <template x-if="!receipt.source_card.name">
                                                <button
                                                        @click="inquiryCardNumber(receipt.id)"
                                                        class="h-8 flex gap-1 items-center bg-primary-50 hover:bg-primary-100 text-sm rounded py-1 px-3.5"
                                                >
                                                    <span class="text-primary-700 font-semibold">استعلام نام</span>
                                                    <span
                                                            :class="{'rotation-animation' : inquiryLoaderIsActive}"
                                                    >
                                                    <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/refresh-blue.svg">
                                                </span>
                                                </button>
                                            </template>
                                            <template x-if="receipt.source_card.name">
                                                <div class="font-medium text-primary-700">
                                                    |
                                                    <span x-text="receipt.source_card.name"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="lg:col-span-6 col-span-full">
                                <div class="flex items-center gap-2">
                                    <div class="size-11 min-w-11 sm:flex hidden items-center justify-center bg-primary-50 rounded-lg">
                                        <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/credit-card-down.svg">
                                    </div>
                                    <div>
                                        <div class="text-primary-500 text-xs mb-1">
                                            کارت یا شبا مقصد (فروشنده):
                                        </div>
                                        <div>
                                            <div class="font-medium text-primary-700">
                                                <span x-text="receipt.destination_card.card_number"></span>
                                                |
                                                <span x-text="receipt.destination_card.name"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- levels -->
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

                    <template x-if="!pageLoaderIsActive && receipt">
                        <div class="bg-white border border-gray-100 rounded-2xl sm:py-8 py-3 md:px-8 px-3 mb-5">
                            <div class="sm:flex justify-center relative">

                                <!--line-->
                                <div class="sm:hidden h-full w-[2px] absolute top-0 right-4 bg-gray-200"></div>

                                <div class="sm:w-[200px] text-center sm:block flex gap-1 sm:mb-0">
                                    <div class="relative z-10 flex justify-center sm:mb-4">
                                        <!-- circle -->
                                        <div class="size-8 min-w-8 rounded-full">
                                            <div class="size-8 bg-primary-100 flex items-center justify-center rounded-full">
                                                <img class="size-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/prev-step.svg">
                                            </div>
                                        </div>

                                        <!--line-->
                                        <div class="sm:block hidden w-1/2 absolute -z-10 top-[50%] -mt-[1px] left-0 h-[2px] bg-gray-200"></div>
                                    </div>
                                    <div class="sm:w-auto w-[calc(100%-36px)] text-center sm:block flex flex-wrap items-center px-0.5">
                                        <div class="text-sm font-semibold text-primary-700 sm:mb-1">
                                            تاریخ ایجاد تراکنش
                                        </div>
                                        <div class="text-xs font-normal text-primary-600 sm:mb-2 sm:mr-0 mr-auto">
                                            <span x-text="receipt.transaction.created_at"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="sm:w-[200px] text-center sm:block flex gap-1 sm:mt-0 mt-4">
                                    <div class="relative z-10 flex justify-center sm:mb-4">
                                        <!-- circle -->
                                        <div class="size-8 min-w-8 rounded-full">
                                            <div class="size-8 bg-primary-100 flex items-center justify-center rounded-full">
                                                <img class="size-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/prev-step.svg">
                                            </div>
                                        </div>

                                        <!--line-->
                                        <div class="sm:block hidden w-1/2 absolute -z-10 top-[50%] -mt-[1px] right-0 h-[2px] bg-gray-200"></div>
                                        <div class="sm:block hidden w-1/2 absolute -z-10 top-[50%] -mt-[1px] left-0 h-[2px] bg-gray-200"></div>
                                    </div>
                                    <div class="sm:w-auto w-[calc(100%-36px)] text-center sm:block flex flex-wrap items-center px-0.5">
                                        <div class="text-sm font-semibold text-primary-700 sm:mb-1">
                                            تاریخ ارسال رسید
                                        </div>
                                        <div class="text-xs font-normal text-primary-600 sm:mb-2 sm:mr-0 mr-auto">
                                            <span x-text="receipt.created_at"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="sm:w-[200px] text-center sm:block flex flex-wrap gap-1 sm:mt-0 mt-4">
                                    <div class="relative z-10 flex justify-center sm:mb-4">
                                        <!-- circle -->
                                        <div class="size-8 rounded-full">
                                            <template x-if="receipt.reviewed.at">
                                                <div class="size-8 bg-primary-100 flex items-center justify-center rounded-full">
                                                    <img class="size-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/prev-step.svg">
                                                </div>
                                            </template>
                                            <template x-if="!receipt.reviewed.at">
                                                <div class="size-8 bg-warning-100 flex items-center justify-center rounded-full shadow-[0_1px_2px_0_#1018280F]">
                                                    <img class="size-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/current-step.svg">
                                                </div>
                                            </template>
                                        </div>

                                        <!--line-->
                                        <div class="sm:block hidden w-1/2 absolute -z-10 top-[50%] -mt-[1px] right-0 h-[2px] bg-gray-200"></div>
                                    </div>
                                    <div class="sm:w-auto w-[calc(100%-36px)] text-center sm:block flex items-center px-0.5">
                                        <div class="text-sm font-semibold text-primary-700 sm:mb-1">
                                            تاریخ تعیین وضعیت
                                        </div>
                                        <div class="text-xs font-normal text-primary-600 sm:mb-2 sm:mb-2 sm:mr-0 mr-auto">
                                            <template x-if="receipt.reviewed.at">
                                                <span x-text="receipt.reviewed.at"></span>
                                            </template>
                                            <template x-if="!receipt.reviewed.at">
                                                <span>نامشخص</span>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </template>
                </div>

                <div>
                    <!-- skeleton -->
                    <template x-if="pageLoaderIsActive">
                        <div class="bg-white border border-gray-100 rounded-2xl sm:py-8 py-3 md:px-8 px-3 mb-5">
                            <div class="grid grid-cols-12 lg:gap-x-10 gap-y-10">
                                <div class="lg:col-span-6 col-span-full">
                                    <div>
                                        <div class="flex items-center md:mb-8 mb-6">
                                            <div class="skeleton h-6 w-36 rounded-full ml-auto"></div>
                                            <div class="skeleton h-6 w-20 rounded-full"></div>
                                        </div>

                                        <template x-for="item in [1,2,3]">
                                            <div class="md:mb-6 mb-5">
                                                <div class="skeleton h-5 w-28 rounded-full mb-2"></div>
                                                <div class="skeleton h-10 rounded-lg"></div>
                                            </div>
                                        </template>

                                        <!-- alert -->
                                        <div class="skeleton h-20 rounded-xl"></div>
                                    </div>
                                </div>
                                <div class="lg:col-span-6 col-span-full">
                                    <div class="bg-[#F1F5F980] rounded-xl p-5">
                                        <div class="h-[440px]">
                                            <div class="skeleton h-full w-72 max-w-full rounded-md mx-auto"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template x-if="!pageLoaderIsActive && receipt">
                        <div class="bg-white border border-gray-100 rounded-2xl sm:py-8 py-3 md:px-8 px-3 mb-5">
                            <div class="grid grid-cols-12 lg:gap-x-10 gap-y-10">
                                <div class="lg:col-span-6 col-span-full">
                                    <div>
                                        <div class="flex items-center md:mb-8 mb-6">
                                            <div class="font-semibold text-lg ml-auto">
                                                بررسی رسید
                                            </div>
                                            <template x-if="receipt.status === 'accepted'">
                                                <div class="inline-block rounded-full bg-success-50 text-xs text-nowrap text-success-700 px-2 py-1">
                                                    تایید شده
                                                </div>
                                            </template>
                                            <template x-if="receipt.status === 'rejected'">
                                                <div class="inline-block rounded-full bg-error-50 text-xs text-nowrap text-error-700 px-2 py-1">
                                                    رد شده
                                                </div>
                                            </template>
                                            <template x-if="receipt.status === 'pending'">
                                                <div class="inline-block rounded-full bg-blue-50 text-xs text-nowrap text-blue-700 px-2 py-1">
                                                    نیازمند بررسی
                                                </div>
                                            </template>
                                        </div>

                                        <div class="md:mb-6 mb-5">
                                            <label class="block text-sm mb-2">شماره پیگیری</label>
                                            <div>
                                                <input
                                                        disabled
                                                        :value="receipt.tracking_number"
                                                        class="w-full !bg-gray-50 border !border-gray-300 text-gray-500 shadow-[0_1px_2px_0_#1018280D] !rounded-lg py-2.5 px-3"
                                                        type="text"
                                                >
                                            </div>
                                        </div>
                                        <div class="md:mb-6 mb-5">
                                            <label class="block text-sm mb-2">مبلغ اظهار شده</label>
                                            <div>
                                                <input
                                                        disabled
                                                        :value="`${gatelandFormatPrice(receipt.amount)} ${(receipt.amount > 0 ? receipt.currency : '')}`"
                                                        class="w-full !bg-gray-50 border !border-gray-300 text-gray-500 shadow-[0_1px_2px_0_#1018280D] !rounded-lg py-2.5 px-3"
                                                        type="text"
                                                >
                                            </div>
                                        </div>
                                        <template x-if="receipt.status === 'accepted'">
                                            <div class="md:mb-6 mb-5">
                                                <label class="block text-sm mb-2">مبلغ تایید شده</label>
                                                <div>
                                                    <input
                                                            disabled
                                                            :value="`${gatelandFormatPrice(receipt.accepted_amount)} ${(receipt.accepted_amount > 0 ? receipt.currency : '')}`"
                                                            class="w-full !bg-gray-50 border !border-gray-300 text-gray-500 shadow-[0_1px_2px_0_#1018280D] !rounded-lg py-2.5 px-3"
                                                            type="text"
                                                    >
                                                </div>
                                            </div>
                                        </template>

                                        <!-- alert -->
                                        <template x-if="receipt.status !== 'rejected'">
                                            <div class="flex gap-3 items-start border border-primary-300 bg-primary-25 rounded-xl text-sm p-4">
                                                <img class="w-4 min-w-4"
                                                     src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/info-square.svg">
                                                <template x-if="receipt.status === 'accepted'">
                                                    <div>
                                                        <div class="text-primary-700 font-semibold mb-0.5">
                                                            رسید تایید شده — غیرقابل بازگشت
                                                        </div>
                                                        <div class="font-normal text-primary-700 pr">
                                                            این رسید در تاریخ
                                                            <span x-text="receipt.reviewed.at"></span>
                                                            توسط
                                                            <span x-text='`"${receipt.reviewed.by}"`'></span>
                                                            تایید شده است و دیگر امکان تغییر وضعیت آن وجود ندارد.
                                                        </div>
                                                    </div>
                                                </template>
                                                <template x-if="receipt.status === 'rejected'">
                                                    <div>
                                                        <div class="text-primary-700 font-semibold mb-0.5">
                                                            رسید رد شده
                                                        </div>
                                                        <div class="font-normal text-primary-700 pr">
                                                            این رسید در تاریخ
                                                            <span x-text="receipt.reviewed.at"></span>
                                                            توسط
                                                            <span x-text='`"${receipt.reviewed.by}"`'></span>
                                                            رد شده است. در صورت نیاز، امکان تغییر وضعیت آن به “تعیین وضعیت مجدد” وجود دارد.
                                                        </div>
                                                    </div>
                                                </template>
                                                <template x-if="receipt.status === 'pending'">
                                                    <div>
                                                        <div class="text-primary-700 font-semibold mb-0.5">
                                                            ثبت نتیجه بررسی رسید
                                                        </div>
                                                        <div class="font-normal text-primary-700 pr">
                                                            شما در حال ثبت نتیجه بررسی این رسید هستید. پس از «تأیید»،
                                                            امکان بازگردانی وجود ندارد و وضعیت تراکنش بر همین اساس به‌روزرسانی می‌شود
                                                            . لطفاً با دقت تصویر رسید و اطلاعات پرداخت را با سفارش تطبیق دهید.
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="receipt.status === 'rejected'">
                                            <div class="flex gap-3 items-start border border-error-300 bg-error-25 rounded-xl text-sm p-4">
                                                <img class="w-4 min-w-4"
                                                     src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/info-square-red.svg">
                                                <div>
                                                        <div class="text-error-700 font-semibold mb-0.5">
                                                            رسید رد شده
                                                        </div>
                                                        <div class="font-normal text-error-700 pr">
                                                            این رسید در تاریخ
                                                            <span x-text="receipt.reviewed.at"></span>
                                                            توسط
                                                            <span x-text='`"${receipt.reviewed.by}"`'></span>
                                                            رد شده است. در صورت نیاز، امکان تغییر وضعیت آن به “تعیین وضعیت مجدد” وجود دارد.
                                                        </div>
                                                    </div>
                                            </div>
                                        </template>

                                        <div
                                                x-show="receipt.status === 'pending'"
                                                class="flex sm:flex-nowrap flex-wrap gap-2.5 text-sm md:mt-6 mt-5"
                                        >
                                            <button
                                                    @click="openAcceptModal(false)"
                                                    class="sm:w-1/2 w-full bg-success-50 hover:bg-success-100 text-success-700 font-medium rounded-lg py-2 px-3.5"
                                            >
                                                تایید تراکنش
                                            </button>
                                            <button
                                                    @click="openRejectModal()"
                                                    class="sm:w-1/2 w-full bg-error-50 hover:bg-error-100 text-error-700 font-medium rounded-lg py-2 px-3.5"
                                            >
                                                رد تراکنش
                                            </button>
                                        </div>

                                        <button
                                                @click="openAcceptModal(true)"
                                                x-show="receipt?.status === 'rejected'"
                                                class="flex items-center justify-center text-nowrap gap-2 w-full text-sm text-gray-700 font-semibold rounded-lg border border-gray-300 hover:bg-gray-100 py-2 px-4 mt-5"
                                        >
                                            <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/reverse-left-gray.svg">
                                            <span>
                                                  تعیین وضعیت مجدد
                                            </span>
                                        </button>
                                    </div>
                                </div>
                                <div class="lg:col-span-6 col-span-full">
                                    <div class="bg-[#F1F5F980] rounded-xl p-5">
                                        <div class="h-[440px] overflow-hidden mb-6">
                                            <img
                                                    class="h-full max-w-full object-cover block rounded-md mx-auto"
                                                    :src="receipt.attachment.url"
                                            >
                                        </div>
                                        <div class="text-center">
                                            <button
                                                    @click="openViewModal()"
                                                    class="inline-flex items-center gap-2 text-gray-600 hover:bg-gray-200 rounded-md py-2 px-4"
                                            >
                                                <img class="h-full max-w-full object-cover block rounded-md mx-auto" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/expand.svg">
                                                مشاهده تصویر
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <template x-if="receipt">
                    <div>
                        <!-- accept receipt modal -->
                        <div
                                x-transition
                                x-cloak
                                class="fixed z-[99999] top-0 left-0 flex items-center justify-center w-full h-full overflow-auto custom-scrollbar py-10 px-4"
                                x-show="modals.accept.active"
                        >
                            <!-- overlay -->
                            <div
                                    @click="modals.accept.active = false"
                                    class="fixed z-10 top-0 left-0 w-full h-full bg-black bg-opacity-50 cursor-pointer"
                            ></div>

                            <!-- modal body -->
                            <div class="bg-white text-gray-900 text-base w-[480px] max-w-full z-20  rounded-xl p-5 my-auto">
                                <div class="mb-3">
                                    <div class="size-12 flex items-center justify-center bg-primary-50 rounded-full">
                                        <div class="size-9 flex items-center justify-center bg-primary-100 rounded-full">
                                            <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/plus-square-blue.svg">
                                        </div>
                                    </div>
                                </div>
                                <div class="font-semibold text-lg mb-1">
                                    تایید رسید
                                </div>

                                <div
                                        x-show="!modals.accept.reAccept"
                                        class="text-sm text-gray-600 mb-6"
                                >
                                    شما در حال تایید رسید
                                    <span x-text="receipt?.id" class="font-semibold"></span>
                                    هستید. آیا از این کار اطمینان دارید؟
                                </div>
                                <div
                                        x-show="modals.accept.reAccept"
                                        class="text-sm text-gray-600 mb-6"
                                >
                                    رسید شماره
                                    <span x-text="receipt?.id" class="font-semibold"></span>
                                    در تاریخ
                                    <span x-text="receipt.reviewed.at"></span>
                                    توسط
                                    <span x-text='`"${receipt.reviewed.by}"`'></span>
                                    رد شده است.
                                    در صورت اطمینان از تغییر وضعیت این رسید از “رد شده” به “تأیید شده”، لطفاً مبلغ مورد تأیید را در کادر زیر وارد کنید.
                                </div>

                                <div class="mb-5">
                                    <label class="block text-sm mb-2">
                                        مبلغ اظهار شده
                                    </label>
                                    <div>
                                        <input
                                                disabled
                                                :value="`${gatelandFormatPrice(receipt.amount)} ${receipt.currency}`"
                                                class="w-full !bg-gray-50 border !border-gray-300 shadow-[0_1px_2px_0_#1018280D] !rounded-lg py-2.5 px-3"
                                                placeholder="شماره کارت را اینجا وارد کنید"
                                                type="text"
                                        >
                                    </div>
                                </div>
                                <div class="mb-10">
                                    <label class="block text-sm mb-2" x-text="`${modals.accept.data.amount.label} (${receipt.currency})`"></label>
                                    <div>
                                        <input
                                                x-model="modals.accept.data.amount.value"
                                                @input="$el.value = gatelandFormatPrice($el.value)"
                                                class="w-full bg-white border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-lg resize-none py-2 px-3"
                                                placeholder="مبلغ مورد تایید را اینجا وارد کنید"
                                        >
                                    </div>
                                    <template x-if="modals.accept.data.amount.value">
                                        <div class="block text-xs text-gray-600 my-1.5 empty:my-0">
                                            <span x-text="gatelandConvertPriceToWords(gatelandPriceToNumber(gatelandFormatPrice(modals.accept.data.amount.value)))"></span>
                                        </div>
                                    </template>
                                    <!--error msg-->
                                    <div
                                            x-text="modals.accept.data.amount.errorMsg"
                                            class="text-xs text-error-300 pt-1.5 empty:pt-0"
                                    >
                                    </div>
                                </div>

                                <div class="flex sm:flex-nowrap flex-wrap justify-center gap-3">
                                    <button
                                            @click="modals.accept.active = false"
                                            class="sm:w-1/2 w-full border border-gray-300 text-gray-700 font-semibold rounded-lg hover:shadow py-2"
                                    >
                                        انصراف
                                    </button>
                                    <button
                                            @click="acceptReceipt(receipt.id)"
                                            class="flex justify-center items-center sm:w-1/2 w-full border bg-primary-600 border-primary-600 text-white font-semibold rounded-lg hover:shadow py-2"
                                    >
                                        <span x-show="!modals.accept.loaderIsActive">تایید رسید</span>
                                        <span
                                                x-show="modals.accept.loaderIsActive"
                                                class="rotation-animation size-6"
                                        >
                                        <img class="h-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/refresh-white.svg">
                                    </span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- reject receipt modal -->
                        <div
                                x-transition
                                x-cloak
                                class="fixed top-0 left-0 z-10 flex items-center justify-center w-full h-full overflow-auto custom-scrollbar text-base p-4"
                                x-show="modals.reject.active"
                        >
                            <!-- overlay -->
                            <div
                                    @click="modals.reject.active = false"
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
                                    رد کردن رسید
                                </div>
                                <div class="text-sm text-gray-600 mb-6">
                                    شما در حال رد کردن رسید
                                    <span x-text="receipt.id" class="font-semibold"></span>
                                    هستید. آیا از این کار اطمینان دارید؟
                                </div>
                                <div class="flex items-center justify-center gap-3">
                                    <button
                                            @click="modals.reject.active = false"
                                            class="w-1/2 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:shadow p-2"
                                    >
                                        انصراف
                                    </button>
                                    <button
                                            @click="rejectReceipt(receipt.id)"
                                            class="w-1/2 border flex items-center justify-center bg-error-600 border-error-600 text-white font-semibold rounded-lg hover:shadow p-2"
                                    >
                                        <span x-show="!modals.reject.loaderIsActive">رد تراکنش</span>
                                        <span
                                                x-show="modals.reject.loaderIsActive"
                                                class="rotation-animation size-6"
                                        >
                                        <img class="h-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/refresh-white.svg">
                                </span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- view receipt modal -->
                        <div
                                x-transition
                                x-cloak
                                class="fixed z-[99999] top-0 left-0 flex items-center justify-center w-full h-full overflow-auto custom-scrollbar py-10 px-4"
                                x-show="modals.view.active"
                        >
                            <!-- overlay -->
                            <div
                                    @click="modals.view.active = false"
                                    class="fixed z-10 top-0 left-0 w-full h-full bg-black bg-opacity-50 cursor-pointer"
                            ></div>

                            <!-- modal body -->
                            <div class="bg-white text-gray-900 w-[480px] max-w-full z-20 rounded-xl py-5 my-auto">
                                <div class="px-5 mb-6">
                                    <div class="mb-3">
                                        <div class="size-12 flex items-center justify-center bg-primary-50 rounded-full">
                                            <div class="size-9 flex items-center justify-center bg-primary-100 rounded-full">
                                                <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/expand-blue.svg">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="font-semibold text-lg mb-1">
                                        مشاهده رسید
                                    </div>
                                </div>
                                <div class="bg-[#F1F5F9] bg-opacity-50 p-5">
                                    <img class="w-full rounded" :src="receipt.attachment.url">
                                </div>
                                <div class="px-5">
                                    <div class="flex sm:flex-nowrap flex-wrap justify-center gap-3">
                                        <button
                                                @click="modals.view.active = false"
                                                class="w-full border border-gray-300 text-gray-700 !text-base font-semibold rounded-lg hover:shadow py-2"
                                        >
                                            بستن
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
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