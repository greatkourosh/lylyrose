<?php

use Carbon\Carbon;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Helper;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Services\GatewayService;

defined( 'ABSPATH' ) || exit;

wp_enqueue_style('custom-style', GATELAND_URL . 'assets/css/style.css', [], GATELAND_VERSION);
wp_enqueue_style('notyf-style', GATELAND_URL . 'assets/css/notyf.min.css', [], GATELAND_VERSION);
wp_enqueue_style( 'persian-datepicker-style', GATELAND_URL . 'assets/css/persian-datepicker.min.css', [], GATELAND_VERSION );

wp_enqueue_script( 'persian-datepicker-script', GATELAND_URL . 'assets/js/persian-datepicker.min.js', ['jquery'], GATELAND_VERSION, true );
wp_enqueue_script( 'persian-date-script', GATELAND_URL . 'assets/js/persian-date.min.js', ['jquery'], GATELAND_VERSION, true );

wp_enqueue_script( 'alpine' );
wp_enqueue_script('notyf-script', GATELAND_URL . 'assets/js/notyf.min.js', [], GATELAND_VERSION, true);
wp_enqueue_script('global-script', GATELAND_URL . 'assets/js/global.js', ['notyf-script', 'persian-date-script'], GATELAND_VERSION, true);
wp_enqueue_script('page-script', GATELAND_URL . 'assets/js/pages/transactions.js', [], GATELAND_VERSION, true);

wp_localize_script('global-script', 'gateland', [
    'root' => esc_url_raw(rest_url()),
    'nonce' => wp_create_nonce('wp_rest'),
]);
?>

<section x-data="gatelandTransactions" class="gateland-container">

    <section class="bg-[#F9FAFB] text-base text-gray-900 py-6 md:pl-5 pl-2.5">

        <div class="container">

            <div class="flex items-center gap-2 flex-wrap mb-6">
                <div class="font-semibold text-lg ml-auto">
                    تراکنش‌ها
                </div>

                <button @click="download('<?php echo wp_create_nonce('wp_rest'); ?>')" class="flex items-center gap-1.5 text-sm text-primary-700 hover:text-primary-500 font-semibold">
                    <span class="min-w-4">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.5 11.5V12.5C16.5 13.9001 16.5 14.6002 16.2275 15.135C15.9878 15.6054 15.6054 15.9878 15.135 16.2275C14.6002 16.5 13.9001 16.5 12.5 16.5H5.5C4.09987 16.5 3.3998 16.5 2.86502 16.2275C2.39462 15.9878 2.01217 15.6054 1.77248 15.135C1.5 14.6002 1.5 13.9001 1.5 12.5V11.5M13.1667 7.33333L9 11.5M9 11.5L4.83333 7.33333M9 11.5V1.5" stroke="currentColor" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </span>
                    <span>
                        دانلود تراکنش‌ها
                    </span>
                </button>
            </div>

            <!-- search -->
            <div class="bg-white border border-gray-300 rounded-xl mb-5">
                <div class="border-b border-gray-300 flex flex-wrap items-center gap-4 md:p-6 p-4">
                    <div class="font-semibold text-lg ml-auto">
                        جستجو بر اساس
                    </div>
                    <button
                            x-cloak
                            x-show="!filtersLoaderIsActive"
                            @click="modals.advanceSearch.active = true"
                            class="flex items-center gap-2 bg-primary-50 hover:bg-primary-100 rounded-[8px] py-2 px-3.5"
                    >
                        <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/search.svg">
                        <span class="font-semibold text-primary-700">جستجو پیشرفته </span>
                        <div x-text="getNumberOfAdvancedFilters()" class="size- flex items-center justify-center bg-blue-100 text-blue-700 rounded-full text-xs pt-1 pb-0.5 px-2"></div>
                    </button>
                </div>

                <!-- skeleton -->
                <template x-if="filtersLoaderIsActive">
                    <div class="grid lg:grid-cols-9 grid-cols-12 lg:gap-2 gap-4 md:p-6 p-4">
                        <template x-for="item in [1,2,3,4]">
                            <div class="lg:col-span-2 sm:col-span-6 col-span-full">
                                <div class="skeleton w-20 h-5 mb-2 rounded-lg"></div>
                                <div class="skeleton h-11 rounded-lg"></div>
                            </div>
                        </template>
                        <div class="lg:col-span-1 col-span-full lg:pt-7">
                            <div class="skeleton h-11 rounded-lg"></div>
                        </div>
                    </div>
                </template>

                <div x-show="!filtersLoaderIsActive" x-cloak="" class="grid lg:grid-cols-9 grid-cols-12 lg:gap-2 gap-4 md:p-6 p-4">
                    <div class="lg:col-span-2 sm:col-span-6 col-span-full">
                        <div>
                            <label class="block text-sm mb-2">شماره تراکنش</label>
                            <input
                                    @keyup.enter="getPageData()"
                                    x-model="tableFilters.transaction_id"
                                    type="number"
                                    class="w-full bg-white border !border-gray-300 shadow-[0_1px_2px_0_#1018280D] !rounded-lg !py-2 !px-3"
                                    placeholder="شماره تراکنش را وارد کنید"
                            >
                        </div>
                    </div>
                    <div class="lg:col-span-2 sm:col-span-6 col-span-full">
                        <div>
                            <label class="block text-sm mb-2">شماره سفارش</label>
                            <input
                                    @keyup.enter="getPageData()"
                                    x-model="tableFilters.order_id"
                                    type="number"
                                    class="w-full bg-white border !border-gray-300 shadow-[0_1px_2px_0_#1018280D] !rounded-lg !py-2 !px-3"
                                    placeholder="شماره سفارش را وارد کنید"
                            >
                        </div>
                    </div>
                    <div class="lg:col-span-2 sm:col-span-6 col-span-full">
                        <label class="block text-sm mb-2">تاریخ</label>
                        <!-- date -->
                        <div id="rangeDateFilter">
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
                                        <span class="text-xs">
                                            <span class='text-gray-400'>از</span>
                                            <span x-text="gatelandFormatDate(tableFilters.from_date, 'L')"></span>
                                        </span>
                                    </template>
                                    <template x-if="tableFilters.to_date">
                                        <span class="text-xs">
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
                    </div>
                    <div class="lg:col-span-2 sm:col-span-6 col-span-full">
                        <div>
                            <label class="block text-sm mb-2">درگاه</label>
                            <div class="gap-1 border border-gray-300 shadow-[0_1px_2px_0_#1018280D] bg-white rounded-lg">
                                <!--multiselect dropdown-->
                                <div
                                        x-data="{open: false}"
                                        @click.outside="open = false"
                                        class="relative h-full"
                                >
                                    <!--active value-->
                                    <div
                                            @click="open = !open"
                                            class="flex items-center gap-2 cursor-pointer py-2 px-3"
                                    >
                                        <div
                                                x-show="tableFilters.gateway_ids.length > 0"
                                                x-text="filters.gateways.filter(item=>tableFilters.gateway_ids.includes(item.key)).map(item=>item.value).join(', ')"
                                                class="min-w-10 line-clamp-1">

                                        </div>
                                        <div x-show="!tableFilters.gateway_ids.length > 0"  class="min-w-10 line-clamp-1">
                                            انتخاب درگاه
                                        </div>

                                        <div
                                                class="duration-300 mr-auto"
                                                :class="{'rotate-180' : open}"
                                        >
                                            <svg width="12" height="8" viewBox="0 0 12 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M1 1.5L6 6.5L11 1.5" stroke="#667085" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </div>
                                    </div>

                                    <!-- dropdown items-->
                                    <div
                                            class="max-h-0 w-[calc(100%+2px)] absolute z-[1] top-[calc(100%+4px)] -left-[1px] border border-gray-200 border-opacity-0 rounded overflow-auto custom-scrollbar duration-300"
                                            :class="{'!max-h-40 !border-opacity-100 shadow bg-white z-[2]' : open}"
                                    >
                                        <div class="bg-white pt-0.5">
                                            <template x-for="(item, index) in filters.gateways">
                                                <div
                                                        @click="tableFilters.gateway_ids.includes(item.key) ? tableFilters.gateway_ids.splice(tableFilters.gateway_ids.indexOf(item.key), 1) : tableFilters.gateway_ids.push(item.key)"
                                                        class="flex gap-2 items-center cursor-pointer hover:text-primary-300 duration-300 p-1.5 mx-1"
                                                        :class="{'border-b' : (index+1 !=  filters.gateways.length)}"
                                                >
                                                    <span x-text="item.value"></span>
                                                    <div
                                                            class="text-primary-300 mr-auto"
                                                            :class="{'opacity-0': !(tableFilters.gateway_ids.includes(item.key)) }"
                                                    >
                                                        <svg width="10" viewBox="0 0 18 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M17 1L6 12L1 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--error msg-->
                            <div class="text-xs text-error-300 pt-1.5 empty:pt-0"></div>
                        </div>
                    </div>

                    <div class="lg:col-span-1 col-span-full lg:pt-7">
                        <button
                                @click="getPageData()"
                                class="block w-full border bg-primary-500 hover:bg-primary-600 text-white text-sm shadow-[0_1px_2px_0_#1018280D] rounded-[8px] py-2.5 px-5 mr-auto"
                        >
                            جستجو
                        </button>
                    </div>
                </div>

            </div>

            <!--table-->
            <div class="bg-white border border-gray-300 rounded-xl overflow-hidden mb-5">

                <div class="flex items-center flex-wrap gap-3 p-4">
                    <div class="text-lg font-semibold order-first ml-auto">
                        لیست تراکنش‌ها
                    </div>
                </div>

                <div class="border-y border-gray-300 py-3 px-4">
                    <!--skeleton-->
                    <template x-if="filtersLoaderIsActive">
                        <div class="inline-flex md:gap-0 gap-2 max-w-full md:border md:rounded-[8px] text-sm text-nowrap font-semibold md:overflow-hidden overflow-auto hidden-scrollbar">
                            <template x-for="item in [1,2,3,4]">
                                <div class="skeleton w-24 h-10 md:rounded-none rounded-full"></div>
                            </template>
                        </div>
                    </template>

                    <template x-if="!filtersLoaderIsActive">
                        <div class="inline-flex md:gap-0 gap-2 max-w-full md:border md:rounded-[8px] text-sm text-nowrap font-semibold md:overflow-hidden overflow-auto hidden-scrollbar">

                            <button
                                    @click="tableFilters.status = null; getPageData()"
                                    class="flex items-center gap-2 md:border-0 md:!border-l border border-gray-300 bg-gray-100 hover:bg-gray-50 md:rounded-none rounded-full md:py-2.5 py-2 md:px-4 px-3.5"
                                    :class="{'bg-gray-100' : (tableFilters.status === null)}"
                            >
                                <span>همه</span>
                                <span
                                        x-text="statuses.find(status=> status.status === 'all')?.count || 0"
                                        class="bg-primary-50 text-primary-700 rounded-full text-xs py-0.5 px-2"
                                >
                            </span>
                            </button>

                            <template x-for="(item, index) in filters.statuses">
                                <button
                                        @click="tableFilters.status = item.key; getPageData()"
                                        class="flex items-center gap-2 md:border-0  border border-gray-300 hover:bg-gray-100 md:rounded-none rounded-full md:py-2.5 py-2 md:px-4 px-3.5"
                                        :class="{'md:!border-l': (index < filters.statuses.length -1), 'bg-gray-100' : (tableFilters.status === item.key)}"
                                >
                                    <span x-text="item.value"></span>
                                    <span
                                            x-text="statuses.find(status=> status.status === item.key) ? gatelandFormatPrice(statuses.find(status=> status.status === item.key)?.count) : 0"
                                            class="bg-primary-50 text-primary-700 rounded-full text-xs py-0.5 px-2"
                                    >
                                </span>
                                </button>
                            </template>

                        </div>
                    </template>
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

                        <template x-if="tableLoaderIsActive">
                            <tbody class="w-full text-sm text-gray-700">
                                <template x-for="row in [1,2,3,4,5]">
                                <tr class="border-b bg-white border-gray-200">
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="skeleton w-10 h-5 rounded-full"></div>
                                    </td>
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="skeleton w-16 h-5 rounded-full"></div>
                                    </td>
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="skeleton w-16 h-5 rounded-full"></div>
                                    </td>
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="skeleton w-20 h-5 rounded-full"></div>
                                    </td>
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="skeleton w-16 h-5 rounded-full"></div>
                                    </td>
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="skeleton w-16 h-5 rounded-full"></div>
                                    </td>
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="skeleton w-16 h-5 rounded-full"></div>
                                    </td>
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="skeleton w-16 h-5 rounded-full"></div>
                                    </td>
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="text-center">
                                            <span class="inline-block skeleton w-5 h-5 rounded-md"></span>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            </tbody>
                        </template>

                        <template x-if="!tableLoaderIsActive">
                            <tbody x-show="tableData.length > 0" class="w-full text-sm text-gray-700">
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
                                            <template x-if="row.order_url">
                                                <a :href="row.order_url">
                                                    <span x-text="row.order_id"></span>
                                                </a>
                                            </template>
                                            <template x-if="!row.order_url">
                                                <span x-text="row.order_id"></span>
                                            </template>
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
                        </template>
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

        <!-- Advance Search Modal -->
        <div
                x-transition
                x-cloak
                class="fixed top-0 left-0 z-[99999] flex items-center justify-center w-full h-full overflow-auto custom-scrollbar py-10 px-4"
                x-show="modals.advanceSearch.active"
        >
            <!-- overlay -->
            <div
                    @click="modals.advanceSearch.active = false"
                    class="fixed z-10 top-0 left-0 w-full h-full bg-black bg-opacity-50 cursor-pointer"
            ></div>

            <!-- body -->
            <div class="bg-white w-[900px] max-w-full z-20  rounded-xl p-5 my-auto">
                <div class="mb-3">
                    <div class="size-12 flex items-center justify-center bg-primary-50 rounded-full">
                        <div class="size-9 flex items-center justify-center bg-primary-100 rounded-full">
                            <img class="size-5" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/search-blue.svg">
                        </div>
                    </div>
                </div>
                <div class="font-semibold text-lg mb-1">
                    جستجو پیشرفته
                </div>
                <div class="text-sm text-gray-600 font-light mb-6">
                    با استفاده از فیلترهای پیشرفته، سریع‌تر به نتیجه دلخواه خود برسید. گزینه‌های جستجو را بر اساس نیاز خود تنظیم کنید.
                </div>

                <div class="grid grid-cols-12 gap-4 mb-10">
                    <div class="md:col-span-4 col-span-full">
                        <div>
                            <div>
                                <label class="block text-sm mb-2">
                                    پذیرنده
                                </label>
                                <div class="gap-1 border border-gray-300 shadow-[0_1px_2px_0_#1018280D] bg-white rounded-lg">
                                    <!--multiselect dropdown-->
                                    <div
                                            x-data="{open: false}"
                                            @click.outside="open = false"
                                            class="relative h-full"
                                    >
                                        <!--active value-->
                                        <div
                                                @click="open = !open"
                                                class="flex items-center gap-2 cursor-pointer py-2 px-3"
                                        >
                                            <div
                                                    x-show="tableFilters.clients.length > 0"
                                                    x-text="filters.clients.filter(item=>tableFilters.clients.includes(item.key)).map(item=>item.value).join(', ')"
                                                    class="min-w-10 line-clamp-1">

                                            </div>
                                            <div x-show="!tableFilters.clients.length > 0"  class="min-w-10 line-clamp-1">
                                                انتخاب پذیرنده
                                            </div>

                                            <div
                                                    class="duration-300 mr-auto"
                                                    :class="{'rotate-180' : open}"
                                            >
                                                <svg width="12" height="8" viewBox="0 0 12 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M1 1.5L6 6.5L11 1.5" stroke="#667085" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </div>
                                        </div>

                                        <!-- dropdown items-->
                                        <div
                                                class="max-h-0 w-[calc(100%+2px)] absolute z-[1] top-[calc(100%+4px)] -left-[1px] border border-gray-200 border-opacity-0 rounded overflow-auto custom-scrollbar duration-300"
                                                :class="{'!max-h-40 !border-opacity-100 shadow bg-white z-[2]' : open}"
                                        >
                                            <div class="bg-white pt-0.5">
                                                <template x-for="(item, index) in filters.clients">
                                                    <div
                                                            @click="tableFilters.clients.includes(item.key) ? tableFilters.clients.splice(tableFilters.clients.indexOf(item.key), 1) : tableFilters.clients.push(item.key)"
                                                            class="flex gap-2 items-center cursor-pointer hover:text-primary-300 duration-300 p-1.5 mx-1"
                                                            :class="{'border-b' : (index+1 !=  filters.clients.length)}"
                                                    >
                                                        <span x-text="item.value"></span>
                                                        <div
                                                                class="text-primary-300 mr-auto"
                                                                :class="{'opacity-0': !(tableFilters.clients.includes(item.key)) }"
                                                        >
                                                            <svg width="10" viewBox="0 0 18 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <path d="M17 1L6 12L1 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!--error msg-->
                                <div class="text-xs text-error-300 pt-1.5 empty:pt-0"></div>
                            </div>
                        </div>
                    </div>
                    <div class="md:col-span-4 col-span-full">
                        <div>
                            <label class="block text-sm mb-2">شماره موبایل</label>
                            <div class="relative">
                                <input
                                        x-model="tableFilters.mobile"
                                        type="number"
                                        class="w-full bg-white border !border-gray-300 shadow-[0_1px_2px_0_#1018280D] !rounded-lg !py-2 !px-3"
                                        placeholder="شماره موبایل"
                                >
                                <button
                                        x-show="tableFilters.mobile"
                                        type="button"
                                        @click="tableFilters.mobile = ''"
                                        class="absolute left-0 top-0 h-full w-8  text-gray-900 hover:text-error-500 rounded-l-lg p-1"
                                >
                            <span class="bg-white size-full flex items-center justify-center">
                                <svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M11.8333 4.66669L5.16663 11.3334M5.16663 4.66669L11.8333 11.3334" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                                </button>
                            </div>
                            <!--error msg-->
                            <div class="text-xs text-error-300 pt-1.5 empty:pt-0"></div>
                        </div>
                    </div>

                    <div class="md:col-span-4 col-span-full">
                        <div>
                            <label class="block text-sm mb-2">شماره کارت</label>
                            <div class="relative">
                                <input
                                        x-model="tableFilters.card_number"
                                        type="number"
                                        class="w-full bg-white border !border-gray-300 shadow-[0_1px_2px_0_#1018280D] !rounded-lg !py-2 !px-3"
                                        placeholder="شماره کارت"
                                >
                                <button
                                        x-show="tableFilters.card_number"
                                        type="button"
                                        @click="tableFilters.card_number = ''"
                                        class="absolute left-0 top-0 h-full w-8  text-gray-900 hover:text-error-500 rounded-l-lg p-1"
                                >
                                <span class="bg-white size-full flex items-center justify-center">
                                    <svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M11.8333 4.66669L5.16663 11.3334M5.16663 4.66669L11.8333 11.3334" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                </button>
                            </div>
                            <!--error msg-->
                            <div class="text-xs text-error-300 pt-1.5 empty:pt-0"></div>
                        </div>
                    </div>

                    <div class="md:col-span-4 col-span-full">
                        <div>
                            <label class="block text-sm mb-2">شناسه مرجع</label>
                            <div class="relative">
                                <input
                                        x-model="tableFilters.gateway_au"
                                        type="number"
                                        class="w-full bg-white border !border-gray-300 shadow-[0_1px_2px_0_#1018280D] !rounded-lg !py-2 !px-3"
                                        placeholder="شناسه مرجع"
                                >
                                <button
                                        x-show="tableFilters.gateway_au"
                                        type="button"
                                        @click="tableFilters.gateway_au = ''"
                                        class="absolute left-0 top-0 h-full w-8  text-gray-900 hover:text-error-500 rounded-l-lg p-1"
                                >
                            <span class="bg-white size-full flex items-center justify-center">
                                <svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M11.8333 4.66669L5.16663 11.3334M5.16663 4.66669L11.8333 11.3334" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                                </button>
                            </div>
                            <!--error msg-->
                            <div class="text-xs text-error-300 pt-1.5 empty:pt-0"></div>
                        </div>
                    </div>

                    <div class="md:col-span-4 col-span-full">
                        <div>
                            <label class="block text-sm mb-2">IP</label>
                            <div class="relative">
                                <input
                                        x-model="tableFilters.ip"
                                        type="number"
                                        class="w-full bg-white border !border-gray-300 shadow-[0_1px_2px_0_#1018280D] !rounded-lg !py-2 !px-3"
                                        placeholder="IP"
                                >
                                <button
                                        x-show="tableFilters.ip"
                                        type="button"
                                        @click="tableFilters.ip = ''"
                                        class="absolute left-0 top-0 h-full w-8  text-gray-900 hover:text-error-500 rounded-l-lg p-1"
                                >
                            <span class="bg-white size-full flex items-center justify-center">
                                <svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M11.8333 4.66669L5.16663 11.3334M5.16663 4.66669L11.8333 11.3334" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                                </button>
                            </div>
                            <!--error msg-->
                            <div class="text-xs text-error-300 pt-1.5 empty:pt-0"></div>
                        </div>
                    </div>

                    <div class="md:col-span-4 col-span-full">
                        <div>
                            <label class="block text-sm mb-2">توضیحات</label>
                            <div class="relative">
                                <input
                                        x-model="tableFilters.description"
                                        type="number"
                                        class="w-full bg-white border !border-gray-300 shadow-[0_1px_2px_0_#1018280D] !rounded-lg !py-2 !px-3"
                                        placeholder="توضیحات"
                                >
                                <button
                                        x-show="tableFilters.description"
                                        type="button"
                                        @click="tableFilters.description = ''"
                                        class="absolute left-0 top-0 h-full w-8  text-gray-900 hover:text-error-500 rounded-l-lg p-1"
                                >
                            <span class="bg-white size-full flex items-center justify-center">
                                <svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M11.8333 4.66669L5.16663 11.3334M5.16663 4.66669L11.8333 11.3334" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                                </button>
                            </div>
                            <!--error msg-->
                            <div class="text-xs text-error-300 pt-1.5 empty:pt-0"></div>
                        </div>
                    </div>
                </div>

                <!-- price filters -->
                <div class="mb-14">
                    <div class="text-gray-900 font-semibold mb-5">جستجو بر اساس قیمت</div>
                    <div class="grid grid-cols-12 gap-4">
                        <div class="md:col-span-4 col-span-full">
                            <div>
                                <label class="block text-sm mb-2">قیمت دقیق</label>
                                <div class="relative">
                                    <input
                                            x-model="tableFilters.amount"
                                            class="w-full bg-white border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-lg py-2 px-3"
                                            placeholder="100000"
                                    >
                                    <div class="absolute left-0 top-0 h-full p-1">
                                        <div class="flex bg-white gap-1 h-full">
                                            <div class="flex items-center text-gray-600 px-2">
                                                تومان
                                            </div>
                                            <button
                                                    x-show="tableFilters.amount"
                                                    type="button"
                                                    @click="tableFilters.amount = ''"
                                                    class="pl-1 flex justify-center items-center text-gray-900 hover:text-error-500 rounded-l-lg"
                                            >
                                                <svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M11.8333 4.66669L5.16663 11.3334M5.16663 4.66669L11.8333 11.3334" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <!--error msg-->
                                <div class="text-xs text-error-300 pt-1.5 empty:pt-0"></div>
                            </div>
                        </div>
                        <div class="md:col-span-4 col-span-full">
                            <div>
                                <label class="block text-sm mb-2">قیمت بیشتر از</label>
                                <div class="relative">
                                    <input
                                            x-model="tableFilters.min_amount"
                                            class="w-full bg-white border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-lg py-2 px-3"
                                            placeholder="100000"
                                    >
                                    <div class="absolute left-0 top-0 h-full p-1">
                                        <div class="flex bg-white gap-1 h-full">
                                            <div class="flex items-center text-gray-600 px-2">
                                                تومان
                                            </div>
                                            <button
                                                    x-show="tableFilters.min_amount"
                                                    type="button"
                                                    @click="tableFilters.min_amount = ''"
                                                    class="pl-1 flex justify-center items-center text-gray-900 hover:text-error-500 rounded-l-lg"
                                            >
                                                <svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M11.8333 4.66669L5.16663 11.3334M5.16663 4.66669L11.8333 11.3334" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <!--error msg-->
                                <div class="text-xs text-error-300 pt-1.5 empty:pt-0"></div>
                            </div>
                        </div>
                        <div class="md:col-span-4 col-span-full">
                            <div>
                                <label class="block text-sm mb-2">قیمت کمتر از</label>
                                <div class="relative">
                                    <input
                                            x-model="tableFilters.max_amount"
                                            class="w-full bg-white border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-lg py-2 px-3"
                                            placeholder="100000"
                                    >
                                    <div class="absolute left-0 top-0 h-full p-1">
                                        <div class="flex bg-white gap-1 h-full">
                                            <div class="flex items-center text-gray-600 px-2">
                                                تومان
                                            </div>
                                            <button
                                                    x-show="tableFilters.max_amount"
                                                    type="button"
                                                    @click="tableFilters.max_amount = ''"
                                                    class="pl-1 flex justify-center items-center text-gray-900 hover:text-error-500 rounded-l-lg"
                                            >
                                                <svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M11.8333 4.66669L5.16663 11.3334M5.16663 4.66669L11.8333 11.3334" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <!--error msg-->
                                <div class="text-xs text-error-300 pt-1.5 empty:pt-0"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-center gap-3">
                    <button
                            @click="modals.advanceSearch.active = false"
                            class="w-1/2 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:shadow py-2"
                    >
                        انصراف
                    </button>
                    <button
                            @click="getPageData(); modals.advanceSearch.active = false"
                            class="w-1/2 border bg-primary-600 border-primary-600 text-white font-semibold rounded-lg hover:shadow  py-2"
                    >
                        جستجو
                    </button>
                </div>
            </div>
        </div>

    </section>

</section>