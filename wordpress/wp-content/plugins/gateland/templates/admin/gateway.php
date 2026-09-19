<?php

use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Helper;
use Nabik\Gateland\Models\Transaction;

defined( 'ABSPATH' ) || exit;

wp_enqueue_style('custom-style', GATELAND_URL . 'assets/css/style.css', [], GATELAND_VERSION);
wp_enqueue_style('notyf-style', GATELAND_URL . 'assets/css/notyf.min.css', [], GATELAND_VERSION);
wp_enqueue_style( 'persian-datepicker-style', GATELAND_URL . 'assets/css/persian-datepicker.min.css', [], GATELAND_VERSION );

wp_enqueue_script( 'persian-datepicker-script', GATELAND_URL . 'assets/js/persian-datepicker.min.js', ['jquery'], GATELAND_VERSION, true );
wp_enqueue_script( 'persian-date-script', GATELAND_URL . 'assets/js/persian-date.min.js', ['jquery'], GATELAND_VERSION, true );
wp_enqueue_script( 'chart-script', GATELAND_URL . 'assets/js/chart.js', [], GATELAND_VERSION, true );

wp_enqueue_script( 'alpine' );
wp_enqueue_script('notyf-script', GATELAND_URL . 'assets/js/notyf.min.js', [], GATELAND_VERSION, true);
wp_enqueue_script('global-script', GATELAND_URL . 'assets/js/global.js', ['notyf-script', 'persian-date-script', 'chart-script'], GATELAND_VERSION, true);
wp_enqueue_script('page-script', GATELAND_URL . 'assets/js/pages/gateway.js', [], GATELAND_VERSION, true);

wp_localize_script( 'global-script', 'gateland', [
    'root'  => esc_url_raw( rest_url() ),
    'nonce' => wp_create_nonce( 'wp_rest' ),
] );
?>

<section x-data="gatelandGateway" class="gateland-container">

    <section class="bg-[#F9FAFB]  text-base text-gray-900 py-6 md:pl-5 pl-2.5">

        <div class="container">

            <div class="mb-6">
                <div class="mb-3">
                    <a href="?page=gateland-gateways" class="inline-flex items-center gap-2 hover:translate-x-0.5">
                        <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/arrow-back.svg">
                        <div class="text-sm text-primary-500 font-semibold">
                            بازگشت به لیست درگاه‌ها
                        </div>
                    </a>
                </div>

                <div class="flex flex-wrap gap-2">
                    <template x-if="!pageLoaderIsActive">
                        <div class="font-semibold text-lg">
                            جزئیات
                            درگاه
                            <span x-text="gateway?.name"></span>
                        </div>
                    </template>

                    <template x-if="pageLoaderIsActive">
                        <div class="skeleton w-36 h-6 rounded-full"></div>
                    </template>

                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mr-auto">

                        <template x-if="!pageLoaderIsActive">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <a
                                        :href="`?page=gateland-gateways-edit&gateway_id=${gateway.id}`"
                                        class="flex items-center text-nowrap gap-2 text-sm text-primary-700 font-semibold rounded-md hover:bg-primary-100 py-2 px-3"
                                >
                                    <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/settings-blue.svg">
                                    <span>
                                تنظیمات
                            </span>
                                </a>
                                <button
                                        @click="openStatusModal()"
                                        class="flex items-center text-nowrap gap-2 text-sm text-primary-700 font-semibold rounded-md hover:bg-primary-100 py-2 px-3"
                                >
                                    <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/power-blue.svg">
                                    <template x-if="gateway?.status === 'active'">
                                        <span>غیرفعال‌سازی </span>
                                    </template>
                                    <template x-if="gateway?.status === 'inactive'">
                                        <span>فعال‌سازی </span>
                                    </template>
                                </button>
                                <button
                                        @click="openDeleteModal()"
                                        class="flex items-center text-nowrap gap-2 text-sm text-primary-700 font-semibold rounded-md hover:bg-primary-100 py-2 px-3"
                                >
                                    <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/trash-blue.svg">
                                    <span>
                                حذف درگاه
                            </span>
                                </button>
                            </div>
                        </template>

                        <!-- date -->
                        <div x-show="!pageLoaderIsActive" id="rangeDateFilter" class="mr-auto">
                                <div
                                        @click="modals.rangeDate.active = true"
                                        class="filter-range-date flex items-center border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-lg duration-300 cursor-pointer bg-white hover:bg-primary-50"
                                >
                                    <div class="border-l border-gray-300 min-w-9 p-2">
                                        <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/calendar.svg">
                                    </div>
                                    <div class="show-value text-sm font-normal  p-2.5">
                                        <template x-if="!tableFilters.from_date && !tableFilters.to_date">
                                            <span class="text-gray-500">انتخاب زمان دلخواه</span>
                                        </template>

                                        <template x-if="tableFilters.from_date">
                                    <span>
                                        <span class='text-gray-400'>از</span>
                                        <span x-text="gatelandFormatDate(tableFilters.from_date, 'L')"></span>
                                    </span>
                                        </template>
                                        <template x-if="tableFilters.to_date">
                                    <span>
                                        <span class='text-gray-400'>تا</span>
                                        <span x-text="gatelandFormatDate(tableFilters.to_date, 'L')"></span>
                                    </span>
                                        </template>
                                    </div>
                                </div>

                                <!-- Modal Range Date -->
                                <div
                                        x-transition
                                        x-cloak
                                        class="fixed top-0 left-0 z-10 flex items-center justify-center w-full h-full overflow-auto custom-scrollbar p-4"
                                        x-show="modals.rangeDate.active"
                                >
                                    <!-- overlay -->
                                    <div
                                            @click="modals.rangeDate.active = false"
                                            class="fixed z-10 top-0 left-0 w-full h-full bg-black bg-opacity-50 cursor-pointer"
                                    ></div>

                                    <!-- body modal -->
                                    <div class="modal bg-white w-[500px] max-w-full z-20  rounded-xl py-5 my-auto">
                                        <div class="text-xl px-5 mb-5">
                                            فیلتر زمانی
                                        </div>
                                        <div class="flex gap-4 text-sm">
                                            <div class="text-primary-500 border-b border-primary-500 px-5 pb-2">
                                                انتخاب تاریخ
                                            </div>
                                            <button
                                                    @click="clearDateFilter(); modals.rangeDate.active = false"
                                                    class="text-primary-500 border-b border-transparent hover:text-error-300 px-5 pb-2 mr-auto"
                                            >
                                                پاک کردن
                                            </button>
                                        </div>
                                        <div class="border-t border-gray-100 pt-5 px-5">
                                            <div class="range-date grid grid-cols-12 gap-5 mb-5">
                                                <div class="md:col-span-6 col-span-full">
                                                    <div class="text-sm text-center font-semibold text-gray-700 border-b border-gray-200 mb-2 pb-2">
                                                        انتخاب تاریخ شروع
                                                    </div>
                                                    <div class="range-date-from"></div>
                                                    <input class="range-date-from-alt hidden" disabled value="1403-09-21">
                                                </div>
                                                <div class="md:col-span-6 col-span-full">
                                                    <div class="text-sm text-center font-semibold text-gray-700 border-b border-gray-200 mb-2 pb-2">
                                                        انتخاب تاریخ پایان
                                                    </div>
                                                    <div class="range-date-to"></div>
                                                    <input class="range-date-to-alt hidden" disabled value="1403-09-28">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-center gap-3 px-5">
                                            <button
                                                    @click="modals.rangeDate.active = false"
                                                    class="w-1/2 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:shadow py-2"
                                            >
                                                انصراف
                                            </button>
                                            <button
                                                    @click="setDateFilter(); modals.rangeDate.active = false"
                                                    class="w-1/2 border bg-primary-600 border-primary-600 text-white font-semibold rounded-lg hover:shadow  py-2"
                                            >
                                                اعمال تغییرات
                                            </button>
                                        </div>
                                    </div>
                                </div>

                            </div>

                        <template x-if="pageLoaderIsActive">
                            <div class="skeleton w-56 h-[42px] rounded-md mr-auto"></div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="mb-5">
                <template x-if="pageLoaderIsActive">
                    <div class="grid grid-cols-12 md:gap-4 gap-y-4 bg-white border border-gray-200 rounded-xl py-4 px-6">
                        <template x-for="item in [1, 2, 3, 4]">
                            <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                                <div class="skeleton w-20 h-4 rounded-full mb-1"></div>
                                <div class="skeleton w-16 h-4 rounded-full"></div>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="!pageLoaderIsActive">
                    <div class="grid grid-cols-12 md:gap-4 gap-y-4 bg-white border border-gray-200 rounded-xl py-4 px-6">
                        <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                            <div class="text-primary-500 text-xs mb-1">نام درگاه:</div>
                            <div class="text-primary-700 text-sm">
                                <span x-text="gateway.name"></span>
                            </div>
                        </div>
                        <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                            <div class="text-primary-500 text-xs mb-1">توضیحات:</div>
                            <div class="text-primary-700 text-sm">
                                <span x-text="gateway.description"></span>
                            </div>
                        </div>
                        <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                            <div class="text-primary-500 text-xs mb-1">وضعیت:</div>
                            <template x-if="gateway.status === 'active'">
                                <div class="inline-block rounded-full bg-success-50 text-xs text-success-700 px-2 py-1">
                                    فعال
                                </div>
                            </template>
                            <template x-if="gateway.status !== 'active'">
                                <div class="inline-block rounded-full bg-error-50 text-xs text-error-700 px-2 py-1">
                                    غیرفعال
                                </div>
                            </template>
                        </div>
                        <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                                <div class="text-primary-500 text-xs mb-1">اولویت:</div>
                                <div class="text-primary-700 text-sm">
                                    <span x-text="gateway.sort"></span>
                                </div>
                            </div>
                    </div>
                </template>
            </div>

            <div>
                <!-- skeleton -->
                <template x-if="pageLoaderIsActive">
                    <div class="grid grid-cols-12 md:gap-4 gap-2 font-semibold text-sm mb-5">
                        <template x-for="item in [1,2,3,4]">
                            <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                                <div class="h-full flex flex-col border border-gray-200 rounded-2xl bg-white md:p-5 p-4">
                                    <div class="flex gap-2 mb-2">
                                        <div class="skeleton h-6 w-20 rounded"></div>
                                        <div class="skeleton size-6 rounded mr-auto"></div>
                                    </div>
                                    <div class="skeleton h-9 w-full rounded mt-auto">
                                    </div>
                                </div>
                        </template>
                    </div>
                </template>
                <template x-if="!pageLoaderIsActive">
                    <div class="grid grid-cols-12 md:gap-4 gap-2 font-semibold text-sm mb-5">
                        <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                            <div class="h-full flex flex-col border border-gray-200 rounded-2xl bg-primary-50 md:p-5 p-4">
                                <div class="flex gap-2 mb-2">
                                    <div class="ml-auto">دریافتی کلی</div>
                                    <img class="size-6 object-contain" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/coins-stacked.svg">
                                </div>
                                <div class="md:text-2xl text-xl mt-auto">
                                    <span x-text="dashboard?.statistics.total_amount"></span>
                                </div>
                            </div>
                        </div>
                        <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                            <div class="h-full flex flex-col border border-gray-200 rounded-2xl bg-white md:p-5 p-4">
                                <div class="flex gap-2 mb-2">
                                    <div class="ml-auto">میانگین زمان پرداخت</div>
                                    <img class="size-6 object-contain" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/speedometer.svg">
                                </div>
                                <div dir="ltr" class="md:text-2xl text-xl text-right mt-auto">
                                    <span x-text="dashboard?.statistics.average_payment_time"></span>
                                </div>
                            </div>
                        </div>
                        <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                            <div class="h-full flex flex-col border border-gray-200 rounded-2xl bg-primary-50 md:p-5 p-4">
                                <div class="flex gap-2 mb-2">
                                    <div class="ml-auto">کل تراکنش‌ها</div>
                                    <img class="size-6 object-contain" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/receipt.svg">
                                </div>
                                <div class="md:text-2xl text-xl mt-auto">
                                    <span x-text="dashboard?.statistics.total_transactions"></span>
                                </div>
                            </div>
                        </div>
                        <div class="lg:col-span-3 sm:col-span-6 col-span-full">
                            <div class="h-full flex flex-col border border-gray-200 rounded-2xl bg-white md:p-5 p-4">
                                <div class="flex gap-2 mb-2">
                                    <div class="ml-auto">نرخ موفقیت</div>
                                    <img class="size-6 object-contain" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/receipt-check.svg">
                                </div>
                                <div class="md:text-2xl text-xl mt-auto">
                                    %
                                    <span x-text="dashboard?.statistics.success_rate"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="grid grid-cols-12 md:gap-4 gap-2 font-semibold overflow-hidden mb-5">

                <div class="lg:col-span-8 md:col-span-7 col-span-full  overflow-auto hidden-scrollbar">
                    <div
                            id="chartTransactions"
                            class="border border-gray-200 bg-white rounded-2xl py-6 px-5"
                    >
                        <div class="md:text-lg font-semibold mb-3">
                            درآمد بر روز
                            <span class="text-sm text-gray-600">(تومان)</span>
                        </div>

                        <div>
                            <div class="h-[315px] relative">
                                <canvas></canvas>

                                <!-- skeleton --->
                                <template x-if="pageLoaderIsActive">
                                    <div class="absolute top-0 left-0 h-full w-full flex justify-around items-end border-b bg-white">
                                        <div class="skeleton h-full w-6 rounded-t"></div>
                                        <div class="skeleton h-1/2 w-6 rounded-t"></div>
                                        <div class="skeleton h-2/3 w-6 rounded-t"></div>
                                        <div class="skeleton h-1/3 w-6 rounded-t"></div>
                                        <div class="skeleton h-full w-6 rounded-t"></div>
                                        <div class="skeleton h-1/2 w-6 rounded-t"></div>
                                    </div>
                                </template>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="lg:col-span-4 md:col-span-5 col-span-full">
                    <div
                            id="chartTransactionsStatus"
                            class="h-full border border-gray-200 bg-primary-50 rounded-2xl py-6 px-5"
                    >
                        <div class="md:text-lg font-semibold mb-3">
                            وضعیت تراکنش‌ها
                        </div>

                        <div class="relative">
                            <div class="relative z-0 size-[200px] mx-auto mb-5">
                                <canvas ></canvas>
                                <div class="absolute -z-10 left-[calc(50%-60px)] top-[calc(50%-60px)] flex flex-col items-center text-sm justify-center size-[120px] rounded-full">
                                    <div
                                            x-text="chartTransactionsStatusData.reduce((acc, obj) => acc + obj.value, 0)"
                                            class="text-gray-800 mb-1"
                                    >
                                    </div>
                                    <div class="text-gray-500">تراکنش‌</div>
                                </div>
                            </div>

                            <div class="w-[200px] mx-auto">
                                <template x-for="(item, index) in chartTransactionsStatusData">
                                    <div class="flex items-center gap-1 text-sm font-normal mb-3">
                                        <div
                                                class="size-1.5 rounded-full"
                                                :style="`background-color: ${item.color}`"
                                        >
                                        </div>
                                        <div x-text="item.label"></div>
                                        <div x-text="item.value" class="mr-auto"></div>
                                    </div>
                                </template>
                            </div>

                            <!-- empty -->
                            <template x-if="!pageLoaderIsActive && chartTransactionsStatusData.length < 1">
                                <div class="absolute top-0 left-0 w-full bg-primary-50">
                                    <div class="flex flex-col items-center justify-center border-[40px] border-primary-100 size-[200px] rounded-full mx-auto mb-5">
                                        <div class="text-gray-800 mb-1">
                                            0
                                        </div>
                                        <div class="text-gray-500">تراکنش‌</div>
                                    </div>

                                    <div class="md:block hidden w-[200px] mx-auto">
                                        <div class="flex items-center gap-1 text-sm font-normal mb-3">
                                            <div class="bg-success-600 size-1.5 rounded-full"></div>
                                            <div>موفق</div>
                                            <div class="mr-auto">0</div>
                                        </div>
                                        <div class="flex items-center gap-1 text-sm font-normal mb-3">
                                            <div class="bg-warning-200 size-1.5 rounded-full"></div>
                                            <div>در انتظار پرداخت</div>
                                            <div class="mr-auto">0</div>
                                        </div>
                                        <div class="flex items-center gap-1 text-sm font-normal mb-3">
                                            <div class="bg-error-200 size-1.5 rounded-full"></div>
                                            <div>ناموفق</div>
                                            <div class="mr-auto">0</div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- skeleton -->
                            <template x-if="pageLoaderIsActive">
                                <div class="absolute top-0 left-0 w-full bg-primary-50">
                                    <div class="skeleton size-[200px] rounded-full mx-auto mb-5">
                                    </div>

                                    <div class="md:block hidden w-[200px] mx-auto">
                                        <template x-for="(item, index) in [1, 2, 3]">
                                            <div class="skeleton w-full h-5 rounded-full mb-3"></div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                    </div>
                </div>

                <template x-if="false">
                    <div class="lg:col-span-8 md:col-span-7 col-span-full  overflow-auto hidden-scrollbar">
                        <div
                                id="chartTransactions"
                                class="border border-gray-200 bg-white rounded-2xl py-6 px-5"
                        >
                            <div class="md:text-lg font-semibold mb-3">
                                درآمد بر روز
                                <span class="text-sm text-gray-600">
                        (تومان)
                    </span>
                            </div>
                            <div class="h-[315px]">
                                <canvas></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-full">
                        <div
                                id="chartTransactionsStatusInYear"
                                class="border border-gray-200 bg-white rounded-2xl py-6 px-5"
                        >
                            <div class="md:flex gap-5 text-sm font-semibold mb-5">
                                <div class="text-gray-900 md:text-lg md:border-l border-gray-400 py-0.5 pl-5 md:mb-0 mb-4">
                                    تراکنش‌ها بر اساس وضعیت در سال
                                </div>
                                <div class="flex items-center md:gap-10 gap-5">

                                    <div class="flex items-center gap-2 md:text-sm text-xs font-normal">
                                        <div class="flex items-center gap-1">
                                            <div
                                                    class="size-1.5 rounded-full"
                                                    :style="`background-color: #039855`"
                                            >
                                            </div>
                                            <div class="font-normal">
                                                موفق
                                            </div>
                                        </div>
                                        <div class="font-bold">
                                            <span x-text="chartTransactionsStatusInYearData.reduce((acc, num) => acc + num.successful, 0)" class="mr-auto"></span>
                                            تراکنش
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 md:text-sm text-xs font-normal">
                                        <div class="flex items-center gap-1">
                                            <div
                                                    class="size-1.5 rounded-full"
                                                    :style="`background-color: #F2A6B3`"
                                            >
                                            </div>
                                            <div class="font-normal">
                                                ناموفق
                                            </div>
                                        </div>
                                        <div class="font-bold">
                                            <span x-text="chartTransactionsStatusInYearData.reduce((acc, num) => acc + num.unsuccessful, 0)" class="mr-auto"></span>
                                            تراکنش
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <div class="h-[300px] w-full mx-auto mb-5">
                                <canvas class="w-full"></canvas>
                            </div>

                        </div>
                    </div>
                </template>

            </div>

            <!--table-->
            <div class="bg-white border border-gray-300 rounded-xl overflow-hidden mb-5">

                <div class="flex items-center flex-wrap gap-3 p-4">
                    <div class="text-lg font-semibold order-first ml-auto">
                        تراکنش‌ها
                    </div>
                    <a href="?page=gateland-transactions" class="text-gray-700 border border-gray-300 text-sm font-semibold rounded-lg hover:bg-gray-100 py-2.5 px-4">
                        مشاهده همه تراکنش‌ها
                    </a>
                </div>

                <div class="overflow-auto custom-scrollbar">
                    <table class="w-full">
                        <thead class="text-sm text-gray-600 text-nowrap">
                        <tr>
                            <td class="bg-gray-100 py-3 px-5">
                                شماره تراکنش
                            </td>
                            <td class="bg-gray-100 py-3 px-5">
                                پذیرنده
                            </td>
                            <td class="bg-gray-100 py-3 px-5">
                                درگاه
                            </td>
                            <td class="bg-gray-100 py-3 px-5">
                                تاریخ ایجاد
                            </td>
                            <td class="bg-gray-100 py-3 px-5">
                                مبلغ
                            </td>
                            <td class="bg-gray-100 py-3 px-5">
                                شناسه سفارش
                            </td>
                            <td class="bg-gray-100 py-3 px-5">
                                شماره موبایل
                            </td>
                            <td class="bg-gray-100 py-3 px-5">
                                وضعیت
                            </td>
                            <td class="bg-gray-100 text-center py-3 px-5">
                                عملیات
                            </td>
                        </tr>
                        </thead>

                        <tbody x-show="tableLoaderIsActive" class="w-full text-sm text-gray-700">
                        <template x-for="row in [1,2,3,4,5]">
                            <tr class="border-b bg-white border-gray-200">
                                <td class="py-4 md:px-5 px-3">
                                    <div class="skeleton w-10 h-5 rounded-full"
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <div class="skeleton w-16 h-5 rounded-full"
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <div class="skeleton w-16 h-5 rounded-full"
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <div class="skeleton w-20 h-5 rounded-full"
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <div class="skeleton w-16 h-5 rounded-full"
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <div class="skeleton w-16 h-5 rounded-full"
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <div class="skeleton w-16 h-5 rounded-full"
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <div class="skeleton w-16 h-5 rounded-full"
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <div class="text-center">
                                        <span class="inline-block skeleton w-5 h-5 rounded-md"></span>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        </tbody>

                        <tbody x-show="!tableLoaderIsActive && tableData.length > 0" class="w-full text-sm text-gray-700">
                        <template x-for="row in tableData">
                            <tr class="border-b bg-white border-gray-200">
                                <td class="py-4 md:px-5 px-3">
                                    <span x-text="row.id"></span>
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <span x-text="row.client"></span>
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <span x-text="row.gateway"></span>
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <span x-text="row.created_at"></span>
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <span x-text="row.amount"></span>
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <span x-text="row.order_id"></span>
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <span x-text="row.mobile"></span>
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <template x-if="row.status === 'paid'">
                                        <div class="inline-block rounded-full bg-success-50 text-xs text-nowrap text-success-700 px-2 py-1">
                                            پرداخت شده
                                        </div>
                                    </template>
                                    <template x-if="row.status === 'failed'">
                                        <div class="inline-block rounded-full bg-error-50 text-xs text-nowrap text-error-700 px-2 py-1">
                                            ناموفق
                                        </div>
                                    </template>
                                    <template x-if="row.status === 'pending'">
                                        <div class="inline-block rounded-full bg-warning-50 text-xs text-nowrap text-warning-700 px-2 py-1">
                                            در انتظار پرداخت
                                        </div>
                                    </template>
                                    <template x-if="row.status === 'refund'">
                                        <div class="inline-block rounded-full bg-gray-100 text-xs text-nowrap text-gray-700 px-2 py-1">
                                            استرداد شده
                                        </div>
                                    </template>
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <div class="text-center">
                                        <a
                                                :href="`?page=gateland-transaction&transaction_id=${row.id}`"
                                                class="size-7 flex items-center justify-center rounded hover:shadow hover:bg-success-100"
                                        >
                                            <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/eye.svg">
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        </tbody>
                    </table>
                </div>

                <div
                        x-show="!tableLoaderIsActive && tableData.length < 1"
                        x-cloak
                        class="flex flex-col items-center justify-center text-center py-14 px-8"
                >
                    <div class="mb-3">
                        <div class="size-12 flex items-center justify-center bg-primary-50 rounded-full mx-auto">
                            <div class="size-9 flex items-center justify-center bg-primary-100 rounded-full">
                                <img class="size-5" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/search-blue.svg">
                            </div>
                        </div>
                    </div>
                    <div class="font-semibold text-gray-900 mb-1">
                        تراکنشی یافت نشد
                    </div>
                    <div class="max-w-[575px] text-gray-600 mb-5">
                        تراکنشی با این مشخصات یافت نشد.
                    </div>
                    <button
                            @click="clearFilter()"
                            class="min-w-fit bg-white border border-gray-300 hover:bg-gray-100 text-sm text-gray-700 font-semibold rounded-[8px] text-nowrap py-2 px-3.5"
                    >
                        <span>حذف فیلتر‌ها</span>
                    </button>
                </div>

                <!-- pagination -->
                <div
                        x-cloak
                        x-show="tableData.length > 0"
                        class="flex items-center justify-end flex-wrap gap-1.5 text-sm text-gray-600 font-normal p-4"
                >

                    <!-- next page -->
                    <button
                            @click="changePage(pagination.currentPage - 1)"
                            class="sm:size-7 size-6 flex items-center justify-center border border-gray-200 hover:bg-gray-100 rounded-md rotate-180 disabled:opacity-50"
                            :disabled="((pagination.totalPage - (pagination.totalPage - 1)) === pagination.currentPage)"
                    >
                        <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/perv.svg">
                    </button>

                    <template x-for="(pageNumber, index) in pagination.items">
                        <div>
                            <template x-if="pageNumber !== '...'">
                                <button
                                        @click="changePage(pageNumber)"
                                        class="sm:size-7 size-6 sm:min-w-7 min-w-6 flex items-center justify-center border border-gray-200 hover:bg-gray-100 rounded-md leading-none pt-0.5 px-1"
                                        :class="{'border-primary-500 text-primary-500' : (pageNumber === pagination.currentPage)}"
                                >
                                    <span x-text="pageNumber"></span>
                                </button>
                            </template>
                            <template x-if="pageNumber === '...'">
                                <span>...</span>
                            </template>
                        </div>
                    </template>

                    <!-- prev page -->
                    <button
                            @click="changePage(pagination.currentPage + 1)"
                            class="sm:size-7 size-6 flex items-center justify-center border border-gray-200 hover:bg-gray-100 rounded-md disabled:opacity-50"
                            :disabled="(pagination.totalPage === pagination.currentPage)"
                    >
                        <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/perv.svg">
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
    </section>

</section>