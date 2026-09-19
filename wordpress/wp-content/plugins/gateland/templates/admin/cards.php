<?php

use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Helper;
use Nabik\Gateland\Models\Transaction;

defined( 'ABSPATH' ) || exit;

wp_enqueue_style('custom-style', GATELAND_URL . 'assets/css/style.css', [], GATELAND_VERSION);
wp_enqueue_style('notyf-style', GATELAND_URL . 'assets/css/notyf.min.css', [], GATELAND_VERSION);
wp_enqueue_style( 'persian-datepicker-style', GATELAND_URL . 'assets/css/persian-datepicker.min.css', [], GATELAND_VERSION );

wp_enqueue_script('notyf-script', GATELAND_URL . 'assets/js/notyf.min.js', [], GATELAND_VERSION, true);
wp_enqueue_script('global-script', GATELAND_URL . 'assets/js/global.js', ['notyf-script'], GATELAND_VERSION, true);
wp_enqueue_script('page-script', GATELAND_URL . 'assets/js/pages/cards.js', [], GATELAND_VERSION, true);
wp_enqueue_script('alpine-script', GATELAND_URL . 'assets/js/alpine.min.js', ['global-script', 'page-script'], GATELAND_VERSION, ['strategy' => 'defer']);

wp_enqueue_script('popper-script', GATELAND_URL . 'assets/js/popper.min.js', [], GATELAND_VERSION, true);
wp_enqueue_script('tippy-script', GATELAND_URL . 'assets/js/tippy-bundle.umd.min.js', ['popper-script'], GATELAND_VERSION, true);

wp_localize_script( 'global-script', 'gateland', [
	'root'  => esc_url_raw( rest_url() ),
	'nonce' => wp_create_nonce( 'wp_rest' ),
] );
?>

<section x-data="cards" class="gateland-container text-base">

    <section class="bg-[#F9FAFB] text-base text-gray-900 py-6 md:pl-5 pl-2.5">

        <div class="container">

            <div class="flex items-center gap-2 flex-wrap mb-5">
                <div class="font-semibold text-lg ml-auto">
                    کارت‌ها
                </div>
                <div
                        x-cloak
                        x-show="!tableLoaderIsActive"
                >
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mr-auto">
                        <button @click="openAddModal()" class="bg-primary-500 hover:bg-primary-600 flex items-center gap-2 text-sm text-white hover:text-white rounded-[8px] py-2 px-3.5">
                            <img class="" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/plus.svg">
                            <span>افزودن کارت جدید</span>
                        </button>
                        <button @click="bulkUpdate()" class="bg-primary-500 hover:bg-primary-600 flex items-center gap-2 text-sm text-white hover:text-white rounded-[8px] py-2 px-3.5">
                            <img class="" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/tick-square.svg">
                            <span>ذخیره سازی تغییرات</span>
                        </button>
                    </div>
                </div>
            </div>

            <!--table-->
            <div class="bg-white border border-gray-300 rounded-xl overflow-hidden mb-5">

                <div class="flex items-center flex-wrap gap-3 p-4">
                    <div class="text-lg font-semibold order-first ml-auto">
                        لیست کارت‌ها
                    </div>
                </div>

                <div class="overflow-auto custom-scrollbar">
                    <table class="w-full">
                        <thead class="text-sm text-gray-600 text-nowrap">
                            <tr>
                            <td class="bg-gray-100 py-3 px-5">
                                شماره کارت | نام و نام خانوادگی
                            </td>
                            <td class="bg-gray-100 py-3 px-5">
                                <div class="flex items-center gap-1">
                                    محدودیت مبلغ تراکنش
                                    <button type="button" class="tooltip-btn w-4 min-w-4" tooltip-text="حداکثر مجموع مبالغ تراکنش‌هایی که با این کارت در یک دوره‌ی ۳۰ روزه انجام می‌شود. این مبلغ تقریبی است.">
                                        <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/help-circle.svg">
                                    </button>
                                </div>
                            </td>
                            <td class="bg-gray-100 py-3 px-5">
                                <div class="flex items-center gap-1">
                                    محدودیت تعداد تراکنش
                                    <button type="button" class="tooltip-btn w-4 min-w-4" tooltip-text="حداکثر تعداد تراکنش‌هایی که با این کارت در یک دوره‌ی ۳۰ روزه انجام می‌شود. این تعداد تقریبی است. ">
                                        <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/help-circle.svg">
                                    </button>
                                </div>
                            </td>
                            <td class="bg-gray-100 py-3 px-5">
                                <div class="flex items-center gap-1">
                                    کارت پشتیبان
                                    <button type="button" class="tooltip-btn w-4 min-w-4" tooltip-text="در صورت پر شدن محدودیت‌های کارت‌ها، تراکنش‌ها به‌طور خودکار به این کارت منتقل خواهد شد.">
                                        <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/help-circle.svg">
                                    </button>
                                </div>
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
                            <template x-for="row in (skeletonIds.length > 1 ? skeletonIds : [1])">
                                <tr class="border-b bg-white border-gray-200">
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="flex items-center gap-2.5 cursor-pointer">
                                            <div class="skeleton w-10 h-10 min-w-10"></div>
                                            <div class="text-nowrap">
                                                <div class="skeleton w-36 h-4 mb-1.5 rounded-full"></div>
                                                <div class="skeleton w-28 h-4 rounded-full"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="skeleton w-44 h-9 rounded-lg"></div>
                                    </td>
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="skeleton w-44 h-9 rounded-lg"></div>
                                    </td>
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="skeleton size-5 rounded-full mx-auto"></div>
                                    </td>
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="skeleton w-16 h-5 rounded-full"></div>
                                    </td>
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="flex items-center gap-2">
                                            <div class="skeleton size-7 rounded-lg"></div>
                                            <div class="skeleton size-7 rounded-lg"></div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>

                        <tbody x-show="!tableLoaderIsActive && tableData.length > 0" class="w-full text-sm text-gray-700">
                            <template x-for="row in tableData">
                                <tr class="border-b bg-white border-gray-200">
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-10 h-10 min-w-10">
                                                <img class="w-full h-full object-cover" src="<?php echo GATELAND_URL . 'assets'; ?>/images/avatar.jpg">
                                            </div>
                                            <div class="text-nowrap">
                                                <div class="font-medium text-gray-900">
                                                    <span dir="ltr" x-text="gatelandFormatCardNumber(row.card_number)"></span>
                                                </div>
                                                <div class="text-gray-600">
                                                    <span x-text="row.name"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="relative min-w-48">
                                            <input
                                                    :value="row.max_amount ? gatelandFormatPrice(row.max_amount) : null"
                                                    @input="$el.value = gatelandFormatPrice($el.value); row.max_amount = gatelandFormatPrice($el.value)"
                                                    class="w-full bg-white border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-lg py-2 px-3"
                                                    placeholder="محدودیت ندارد"
                                            >
                                            <div class="absolute left-0 top-0 h-full p-1">
                                                <div class="flex bg-white gap-1 h-full">
                                                    <div class="flex items-center text-gray-600 px-2">
                                                        تومان
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="min-w-48">
                                            <input
                                                    :value="row.max_quantity ? gatelandFormatPrice(row.max_quantity) : null"
                                                    @input="$el.value = gatelandFormatPrice($el.value); row.max_quantity = gatelandFormatPrice($el.value);"
                                                    class="w-full bg-white border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-lg py-2 px-3"
                                                    placeholder="محدودیت ندارد"
                                            >
                                        </div>
                                    </td>
                                    <td class="py-4 md:px-5 px-3">
                                        <template x-if="row.is_failover">
                                            <div class="size-5 bg-primary-50 border border-primary-600 rounded-full flex items-center justify-center mx-auto">
                                                <div class="size-2 rounded-full bg-primary-600"></div>
                                            </div>
                                        </template>
                                        <template x-if="!row.is_failover">
                                            <div
                                                    @click="toggleIsfailover(row.id)"
                                                    class="size-5 bg-gray-50 border border-gray-300 rounded-full flex items-center justify-center cursor-pointer mx-auto"
                                            >
                                                <div class="size-2 rounded-full bg-gray-300"></div>
                                            </div>
                                        </template>
                                    </td>
                                    <td class="py-4 md:px-5 px-3">
                                        <template x-if="row.status === 'active'">
                                            <div class="flex items-center md:justify-start justify-end gap-0.5">
                                                <div
                                                        @click="toggleStatus(row.id, 'inactive')"
                                                        class="bg-primary-600 relative inline-flex w-9 h-5 rounded-xl  z-1 cursor-pointer"
                                                >
                                                    <div class="right-0.5 size-4 absolute top-0.5 bg-white rounded-full z-10 shadow-[0_1px_3px_0_#1018281A]">
                                                    </div>
                                                </div>
                                                <div class="md:inline-block hidden rounded-full text-center text-nowrap text-xs text-gray-900 0 px-2 py-1">
                                                    فعال
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="row.status === 'inactive'">
                                                <div class="w-full flex items-center md:justify-start justify-end gap-0.5">
                                                    <div
                                                            @click="toggleStatus(row.id, 'active')"
                                                            class="bg-gray-100 relative inline-flex w-9 h-5 rounded-xl  z-1 cursor-pointer"
                                                    >
                                                        <div class="left-0.5 size-4 absolute top-0.5 bg-white rounded-full z-10 shadow-[0_1px_3px_0_#1018281A]">
                                                        </div>
                                                    </div>
                                                    <div class="md:inline-block hidden rounded-full text-center text-nowrap text-xs text-gray-900 0 px-2 py-1">
                                                        غیرفعال
                                                    </div>
                                                </div>
                                            </template>
                                    </td>
                                    <td class="py-4 md:px-5 px-3">
                                        <div class="flex items-center gap-1">
                                            <button
                                                    @click="openEditModal(row)"
                                                    class="size-7 flex items-center justify-center rounded hover:shadow hover:bg-primary-100"
                                            >
                                                <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/edit.svg">
                                            </button>
                                            <button
                                                    @click="openDeleteModal(row)"
                                                    class="size-7 flex items-center justify-center rounded hover:shadow hover:bg-error-100"
                                            >
                                                <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/trash.svg">
                                            </button>
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
                        کارتی یافت نشد
                    </div>
                </div>

            </div>

            <!-- add card modal -->
            <div
                    x-transition
                    x-cloak
                    class="fixed z-[99999] top-0 left-0 flex items-center justify-center w-full h-full overflow-auto custom-scrollbar py-10 px-4"
                    x-show="modals.add.active"
            >
                <!-- overlay -->
                <div
                        @click="modals.add.active = false"
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
                        افزودن کارت
                    </div>
                    <div class="text-sm text-gray-600 mb-6">
                        شما در حال افزودن کارت به لیست کارت‌های موجود برای کارت به کارت هستید.
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm mb-2">شماره کارت</label>
                        <div>
                            <input
                                    x-model="modals.add.data.cardNumber.value"
                                    @input="modals.add.data.cardNumber.value = gatelandFormatCardNumber(modals.add.data.cardNumber.value)"
                                    maxlength="19"
                                    minlength="19"
                                    class="w-full bg-white border !border-gray-300 shadow-[0_1px_2px_0_#1018280D] !rounded-lg py-2 px-3"
                                    placeholder="شماره کارت را اینجا وارد کنید"
                                    type="text"
                            >
                        </div>
                        <!--error msg-->
                        <div
                                x-text="modals.add.data.cardNumber.errorMsg"
                                class="text-xs text-error-300 pt-1.5 empty:pt-0"
                        >
                        </div>
                    </div>
                    <div class="mb-10">
                        <label class="block text-sm mb-2">نام و نام خانوادگی صاحب کارت</label>
                        <div>
                            <input
                                    x-model="modals.add.data.name.value"
                                    rows="5"
                                    class="w-full bg-white border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-lg resize-none py-2 px-3"
                                    placeholder="نام و نام خانوادگی صاحب کارت را اینجا وارد کنید"
                            >
                        </div>
                        <!--error msg-->
                        <div
                                x-text="modals.add.data.name.errorMsg"
                                class="text-xs text-error-300 pt-1.5 empty:pt-0"
                        >
                        </div>
                    </div>

                    <div class="flex sm:flex-nowrap flex-wrap justify-center gap-3">
                        <button
                                @click="modals.add.active = false"
                                class="sm:w-1/2 w-full border border-gray-300 text-gray-700 font-semibold rounded-lg hover:shadow py-2"
                        >
                            انصراف
                        </button>
                        <button
                                @click="addCard()"
                                class="flex justify-center items-center sm:w-1/2 w-full border bg-primary-600 border-primary-600 text-white font-semibold rounded-lg hover:shadow py-2"
                        >
                            افزودن کارت
                        </button>
                    </div>
                </div>
            </div>

            <!-- edit card modal -->
            <div
                    x-transition
                    x-cloak
                    class="fixed z-[99999] top-0 left-0 flex items-center justify-center w-full h-full overflow-auto custom-scrollbar py-10 px-4"
                    x-show="modals.edit.active"
            >
                <!-- overlay -->
                <div
                        @click="modals.edit.active = false"
                        class="fixed z-10 top-0 left-0 w-full h-full bg-black bg-opacity-50 cursor-pointer"
                ></div>

                <!-- modal body -->
                <div class="bg-white text-gray-900 text-base w-[480px] max-w-full z-20  rounded-xl p-5 my-auto">
                    <div class="mb-3">
                        <div class="size-12 flex items-center justify-center bg-primary-50 rounded-full">
                            <div class="size-9 flex items-center justify-center bg-primary-100 rounded-full">
                                <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/edit-blue.svg">
                            </div>
                        </div>
                    </div>
                    <div class="font-semibold text-lg mb-1">
                        ویرایش اطلاعات کارت
                    </div>
                    <div class="text-sm text-gray-600 mb-6">
                        شما در حال ویرایش اطلاعات کارت
                        <span dir="ltr" x-text="gatelandFormatCardNumber(modals.edit.card?.card_number)" class="font-semibold"></span>
                        هستید.
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm mb-2">شماره کارت</label>
                        <div>
                            <input
                                    :value="gatelandFormatCardNumber(modals.edit.card?.card_number)"
                                    class="w-full border !border-gray-300 shadow-[0_1px_2px_0_#1018280D] !rounded-lg bg-gray-100 py-2 px-3"
                                    placeholder="شماره کارت را اینجا وارد کنید"
                                    type="text"
                                    disabled
                            >
                        </div>
                    </div>
                    <div class="mb-10">
                        <label class="block text-sm mb-2">نام و نام خانوادگی صاحب کارت</label>
                        <div>
                            <input
                                    x-model="modals.edit.data.name.value"
                                    rows="5"
                                    class="w-full bg-white border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-lg resize-none py-2 px-3"
                                    placeholder="نام و نام خانوادگی صاحب کارت را اینجا وارد کنید"
                            >
                        </div>
                        <!--error msg-->
                        <div
                                x-text="modals.edit.data.name.errorMsg"
                                class="text-xs text-error-300 pt-1.5 empty:pt-0"
                        >
                        </div>
                    </div>

                    <div class="flex sm:flex-nowrap flex-wrap justify-center gap-3">
                        <button
                                @click="modals.edit.active = false"
                                class="sm:w-1/2 w-full border border-gray-300 text-gray-700 font-semibold rounded-lg hover:shadow py-2"
                        >
                            انصراف
                        </button>
                        <button
                                @click="updateCard()"
                                :disabled="modals.edit.loaderIsActive"
                                class="flex justify-center items-center sm:w-1/2 w-full border bg-primary-600 border-primary-600 text-white font-semibold rounded-lg hover:shadow py-2"
                        >
                            <template x-if="!modals.edit.loaderIsActive">
                                <span>ویرایش کارت</span>
                            </template>
                            <template x-if="modals.edit.loaderIsActive">
                                <div class="rotation-animation size-6">
                                    <img class="size-6" src="<?php echo GATELAND_URL ?>assets/images/icons/refresh-white.svg">
                                </div>
                            </template>
                        </button>
                    </div>
                </div>
            </div>

            <!-- delete card modal -->
            <div
                    x-transition
                    x-cloak
                    class="fixed top-0 left-0 z-10 flex items-center justify-center w-full h-full overflow-auto custom-scrollbar text-base p-4"
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
                        حذف کارت
                    </div>
                    <div class="text-sm text-gray-600 mb-6">
                        شما در حال حذف کارت
                        <span dir="ltr" x-text="gatelandFormatCardNumber(modals.delete.card?.card_number)" class="font-semibold"></span>
                        هستید.
                        <br>
                        آیا از این کار اطمینان دارید؟
                    </div>
                    <div class="flex items-center justify-center gap-3">
                        <button
                                @click="modals.delete.active = false"
                                class="w-1/2 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:shadow p-2"
                        >
                            انصراف
                        </button>
                        <button @click="deleteCard()" class="w-1/2 border bg-error-600 border-error-600 text-white font-semibold rounded-lg hover:shadow p-2">
                            حذف کارت
                        </button>
                    </div>
                </div>
            </div>


        </div>

    </section>

</section>