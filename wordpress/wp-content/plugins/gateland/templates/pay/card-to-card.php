<?php

use Nabik\Gateland\Models\Transaction;

defined( 'ABSPATH' ) || exit;

/** @var Transaction $transaction */

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>پرداخت کارت به کارت</title>
	<link rel='stylesheet' href='./assets/css/style.css' media='all'/>

	<script>
        var gateland = <?php echo json_encode( [
			'root'              => esc_url_raw( rest_url() ),
			'transaction_token' => $transaction->token,
		] ); ?>;
	</script>

	<link rel="stylesheet" href="<?php echo esc_url( GATELAND_URL ) . '/assets/css/style.css' ?>">
	<link rel="stylesheet" href="<?php echo esc_url( GATELAND_URL ) . '/assets/css/notyf.min.css' ?>">

	<script src="<?php echo esc_url( GATELAND_URL ) . '/assets/js/notyf.min.js' ?>"></script>
	<script src="<?php echo esc_url( GATELAND_URL ) . '/assets/js/pages/card-to-card.js' ?>"></script>
	<script src="<?php echo esc_url( GATELAND_URL ) . '/assets/js/alpine.min.js' ?>" defer></script>
</head>
<body>

<section x-data="cardToCard" class="gateland-container">

    <section
            x-show="addReceiptIsActive"
            x-transition
            class="bg-[#EEF1F7] text-[#344054]"
    >
        <div class="container">

            <section  x-show="pageLoaderIsActive" class="min-h-screen flex items-center justify-center">
                <div class="w-[1200px] max-w-full lg:shadow-[0_28.63px_71.56px_-40.55px_#10162266] lg:rounded-3xl overflow-hidden mx-auto md:my-6 my-3">
                    <div class="bg-white flex items-center md:gap-3 gap-2 border border-[#E6E9F0] lg:rounded-t-3xl lg:rounded-b-none rounded-xl lg:py-5 py-3 lg:px-8 px-3 lg:mb-0 mb-3">
                        <div class="skeleton rounded-md size-6"></div>
                        <div class="flex items-center gap-2.5">
                            <div class="md:size-14 size-10 skeleton md:rounded-[10px] rounded-md"></div>
                            <div>
                                <div class="md:w-28 w-20 md:h-[28px] h-5 skeleton rounded-md"></div>
                                <div class="md:w-20 w-16 md:h-5 h-4 skeleton rounded-md mt-1"></div>
                            </div>
                        </div>
                        <div class="md:w-28 w-16 md:h-11 h-5 rounded-md skeleton mr-auto"></div>
                    </div>

                    <div class="skeleton lg:hidden rounded-xl h-10 mb-3"></div>

                    <div class="lg:bg-white lg:border-x lg:border-[#E6E9F0] relative">
                        <div class="grid grid-cols-12">
                            <div class="lg:col-span-6 col-span-full">
                                <div class="lg:bg-white h-full lg:py-5 lg:px-8">
                                    <div>
                                        <div class="lg:block flex items-center gap-2 text-center lg:bg-[linear-gradient(180deg,#F0F7FF_0%,#FFFFFF_100%)] bg-white border lg:border-[#DDE7FF] lg:rounded-2xl rounded-xl lg:p-5 p-3 lg:mb-6 mb-[198px]">
                                            <div class="skeleton h-5 w-20 rounded-full lg:mb-1 lg:mx-auto"></div>
                                            <div class="skeleton lg:block hidden h-9 w-44 max-w-full rounded-full mx-auto lg:mb-1"></div>
                                            <div class="skeleton lg:h-5 h-7 w-20 rounded-full lg:mx-auto mr-auto"></div>
                                        </div>
                                        <div class="bg-white lg:border-0 border border-[#E6E9F0] lg:rounded-none rounded-xl lg:p-0 p-4 lg:mb-0 mb-3">
                                            <div class="skeleton h-6 w-32 rounded-md mb-3"></div>
                                            <div class="mb-6">
                                                <template x-for="item in [1, 2, 3]">
                                                    <div class="mb-4">
                                                        <div class="skeleton h-5 w-20 rounded-full mb-1.5"></div>
                                                        <div class="skeleton h-10 w-full rounded-xl"></div>
                                                    </div>
                                                </template>

                                                <div class="mb-4">
                                                    <div class="skeleton h-5 w-20 rounded-full mb-1.5"></div>
                                                    <div class="skeleton h-20 w-full rounded-xl"></div>
                                                </div>
                                            </div>
                                            <div class="skeleton h-10 w-full rounded-xl "></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="lg:col-span-6 col-span-full">
                                <div class="lg:bg-[#F8FAFC] h-full lg:py-5 lg:px-8">
                                    <div class="skeleton h-10 lg:block hidden rounded-xl mb-6"></div>

                                    <div>
                                        <div class="skeleton lg:block hidden h-6 w-32 rounded-md mb-3"></div>
                                        <div class="lg:static absolute top-[62px] left-0 w-full">
                                            <div class="skeleton lg:h-[180px] h-[172px] lg:rounded-[20px] rounded-xl mb-6"></div>
                                        </div>
                                    </div>

                                    <div class="bg-white border border-[#EAECF0] text-sm rounded-2xl p-4">
                                        <div class="skeleton h-6 w-32 max-w-full rounded-full mb-6"></div>
                                        <div class="space-y-3">
                                            <template x-for="item in [1, 2, 3, 4]">
                                                <div class="skeleton h-5 w-full rounded-full"></div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:bg-white text-center text-[#7A8696] lg:text-sm text-xs lg:border border-[#E6E9F0] lg:rounded-b-3xl md:rounded-t-none py-5 lg:px-8 px-3 lg:mb-0 mb-2">
                        قدرت‌گرفته از گیت‌لند
                    </div>
                </div>
            </section>

            <section  x-show="!pageLoaderIsActive" class="min-h-screen flex items-center justify-center">
                <div class="w-[1200px] max-w-full lg:shadow-[0_28.63px_71.56px_-40.55px_#10162266] lg:rounded-3xl overflow-hidden mx-auto md:my-6 my-3">

                    <div class="bg-white flex items-center md:gap-3 gap-2 border border-[#E6E9F0] lg:rounded-t-3xl lg:rounded-b-none rounded-xl lg:py-5 py-3 lg:px-8 px-3 lg:mb-0 mb-3">
                        <div class="flex items-center gap-2.5">
                            <template x-if="pageDetails?.site.logo">
                                <a :href="pageDetails?.site.url" class="md:size-14 size-10 overflow-hidden md:rounded-[10px] rounded-md">
                                    <img  class="w-full h-full object-cover" :src="pageDetails.site.logo">
                                </a>
                            </template>
                            <template x-if="!pageDetails?.site.logo">
                                <a :href="pageDetails?.site.url" class="md:size-14 size-10 flex items-center justify-center bg-[linear-gradient(130.24deg,#FF7A45_0%,#FF4D6D_99.96%)] md:rounded-[10px] rounded-md">
                                    <span class="md:text-[28px] text-base text-white"  x-text="pageDetails?.site.name ? pageDetails?.site.name.charAt(0) : ''"></span>
                                </a>
                            </template>
                            <div>
                                <a :href="pageDetails?.site.url" class="font-bold md:text-lg text-sm">
                                    <span x-text="pageDetails?.site.name"></span>
                                </a>
                                <div class="flex items-center gap-1 md:mt-0 -mt-0.5">
                                    <div class="size-1.5 rounded-full bg-success-500"></div>
                                    <span class="md:text-sm text-xs">کارت به کارت</span>
                                </div>
                            </div>
                        </div>
                        <div class="sm:bg-[#F8FAFC] sm:border border-[#E6E9F0] flex items-center gap-1 sm:rounded-xl sm:py-2.5 sm:px-4 mr-auto">
                            <span class="sm:block hidden text-[#7A8696]">سفارش</span>
                            <span class="text-[#3A4658] font-semibold md:text-base text-sm" dir="ltr" x-text="'#' + pageDetails?.order_id"></span>
                        </div>
                    </div>

                    <div class="lg:hidden flex items-center gap-2 bg-[#EAEFFF] rounded-xl border border-[#D9E2FF] text-sm text-primary-700 font-semibold p-3 mb-3">
                        <span>زمان باقی‌مانده برای ثبت رسید:</span>
                        <div x-show="time.textTime" class="mr-auto">
                            <div class="flex text-center">
                                <template
                                        x-for="(char) in time.textTimeSeconds.split('').map(Number).reverse()">
                                    <span x-text="char" class="w-3"></span>
                                </template>
                                :
                                <template
                                        x-for="char in time.textTimeMinutes.split('').map(Number).reverse()">
                                    <span x-text="char" class="w-3"></span>
                                </template>
                                :
                                <template
                                        x-for="(char) in time.textTimeHours.split('').map(Number).reverse()">
                                    <span x-text="char" class="w-3"></span>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="lg:bg-white lg:border-x lg:border-[#E6E9F0] relative">
                        <div class="grid grid-cols-12">
                           <div class="lg:col-span-6 col-span-full">
                               <div class="lg:bg-white h-full lg:py-5 lg:px-8">
                                  <div>
                                      <div class="lg:block flex items-center gap-2 text-center lg:bg-[linear-gradient(180deg,#F0F7FF_0%,#FFFFFF_100%)] bg-white border lg:border-[#DDE7FF] lg:rounded-2xl rounded-xl lg:p-5 p-3 lg:mb-6 mb-[198px]">
                                          <div class="text-gray-600 text-sm lg:mb-1">مبلغ قابل پرداخت</div>
                                          <div class="flex items-center justify-center gap-2 lg:mr-0 mr-auto lg:mb-1">
                                              <div class="font-semibold">
                                                  <span class="lg:text-[24px]" x-text="gatelandFormatPrice(pageDetails?.amount)"></span>
                                                  <span class="lg:text-base text-sm" x-text="pageDetails?.currency"></span>
                                              </div>
                                              <button
                                                      @click="gatelandCopyToClipboard($el, 'مبلغ قابل پرداخت')"
                                                      :data-copy="pageDetails?.amount"
                                                      class="lg:flex hidden items-center justify-center gap-1.5 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 px-2.5 py-1.5"
                                              >
                                                  <img class="w-[18px] min-w-[18px]" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/copy-gray.svg">
                                                  <span class="text-copy text-xs">کپی</span>
                                              </button>
                                          </div>
                                          <div class="hidden text-gray-500 font-normal">
                                              معادل ۱۲٬۰۰۰٬۰۰۰ تومان
                                          </div>
                                      </div>
                                      <div class="bg-white lg:border-0 border border-[#E6E9F0] lg:rounded-none rounded-xl lg:p-0 p-4 lg:mb-0 mb-3">
                                          <div class="font-bold mb-3">اطلاعات واریز</div>
                                          <div class="mb-6">
                                              <div class="mb-4">
                                                  <label class="block text-sm text-gray-700 mb-1.5">
                                                      کارت یا شبایی که از آن واریز کرده‌اید
                                                  </label>
                                                  <input
                                                          x-model="inputs.cardNumber.value"
                                                          @input="inputs.cardNumber.value = gatelandFormatCardNumber(inputs.cardNumber.value, true)"
                                                          maxlength="29"
                                                          minlength="29"
                                                          type="text"
                                                          placeholder="شماره کارت یا شبایی که از آن پرداخت را انجام داده‌اید"
                                                          class="block border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-xl text-gray-500 w-full py-2 px-3"
                                                  >
                                                  <!-- error msg -->
                                                  <div
                                                          x-text="inputs.cardNumber.errorMsg"
                                                          class="text-error-400 text-sm empty:pt-0 pt-1"
                                                  >
                                                  </div>
                                              </div>
                                              <div class="mb-4">
                                                  <label class="block text-sm text-gray-700 mb-1.5">
                                                      شماره پیگیری بانکی
                                                  </label>
                                                  <input
                                                          x-model="inputs.trackingNumber.value"
                                                          type="number"
                                                          placeholder="شماره پیگیری یا شماره مرجع تراکنش را وارد کنید"
                                                          class="block border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-xl text-gray-500 w-full py-2 px-3"
                                                  >
                                                  <!-- error msg -->
                                                  <div
                                                          x-text="inputs.trackingNumber.errorMsg"
                                                          class="text-error-400 text-sm empty:pt-0 pt-1"
                                                  >
                                                  </div>
                                              </div>
                                              <div class="mb-4">
                                                  <label class="block text-sm text-gray-700 mb-1.5">
                                                      مبلغ واریزی
                                                      <span x-text="pageDetails?.currency ? `(${pageDetails?.currency})` : ''"></span>
                                                  </label>
                                                  <input
                                                          x-model="inputs.amount.value"
                                                          @input="$el.value = gatelandFormatPrice($el.value)"
                                                          placeholder="مبلغ تراکنش واریز شده را وارد کنید"
                                                          class="block border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-xl text-gray-500 w-full py-2 px-3"
                                                  >
                                                  <template x-if="inputs.amount.value">
                                                      <div class="block text-xs text-gray-600 my-1.5 empty:my-0">
                                                          <span x-text="gatelandConvertPriceToWords(gatelandPriceToNumber(gatelandFormatPrice(inputs.amount.value)))"></span>
                                                      </div>
                                                  </template>
                                                  <!-- error msg -->
                                                  <div
                                                          x-text="inputs.amount.errorMsg"
                                                          class="text-error-400 text-sm empty:pt-0 pt-1"
                                                  >
                                                  </div>
                                              </div>
                                              <div>
                                                  <label class="block text-sm text-gray-700 mb-1.5">
                                                      تصویر رسید
                                                  </label>
                                                  <div
                                                          @click="$refs.inputFile.click()"
                                                          class="border border-dashed border-gray-300 bg-[#F5FBFF] shadow-[0_1px_2px_0_#1018280D] flex md:flex-row flex-col md:justify-start justify-center items-center gap-3 md:text-right text-center cursor-pointer hover:bg-gray-100 rounded-xl py-2.5 px-3"
                                                  >
                                                      <div class="size-10 min-w-10 rounded-full">
                                                          <img class="w-full mx-auto" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/upload-blue.svg">
                                                      </div>
                                                      <template x-if="!inputs.receiptImage.fileName">
                                                          <div class="text-gray-600 mb-1">
                                                              <div class="text-sm font-semibold">برای آپلود کلیک کنید.</div>
                                                              <div class="md:text-base text-sm">
                                                                  فرمت‌های قابل قبول: png ،jpg ،jpeg |
                                                                  حداکثر
                                                                  <span x-text="pageDetails?.max_file_size"></span>
                                                                  مگابایت
                                                              </div>
                                                          </div>
                                                      </template>
                                                      <template x-if="inputs.receiptImage.fileName">
                                                          <div x-text="inputs.receiptImage.fileName"
                                                               class="max-w-full line-clamp-1 md:text-lg text-primary-600"></div>
                                                      </template>
                                                      <input
                                                              x-ref="inputFile"
                                                              @change="uploadReceiptImage($event)"
                                                              type="file"
                                                              accept=".png,.jpg,.jpeg"
                                                              class="hidden text-gray-500 w-full"
                                                      >
                                                  </div>
                                                  <!-- error msg -->
                                                  <div
                                                          x-text="inputs.receiptImage.errorMsg"
                                                          class="text-error-400 text-sm empty:pt-0 pt-1"
                                                  >
                                                  </div>
                                              </div>
                                          </div>
                                          <div class="grid grid-cols-12 gap-x-4 gap-y-2">
                                              <div
                                                      class="xl:col-span-6 lg:col-span-full md:col-span-6 col-span-full"
                                                      :class="{'!col-span-full' : tableData.length < 1}"
                                              >
                                                  <button
                                                          @click="uploadReceipt()"
                                                          type="submit"
                                                          class="flex justify-center items-center bg-primary-600 hover:bg-primary-700 border border-primary-600 text-white font-semibold w-full rounded-lg py-2.5 px-4"
                                                  >
                                                      <span x-show="!uploadLoaderIsActive">ثبت رسید</span>
                                                          <span
                                                                  x-show="uploadLoaderIsActive"
                                                                  class="rotation-animation size-6"
                                                          >
                                                        <img class="h-full"
                                                             src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/refresh-white.svg">
                                                    </span>
                                                  </button>
                                              </div>
                                              <div
                                                      x-show="tableData.length > 0"
                                                      x-cloak
                                                      class="xl:col-span-6 lg:col-span-full md:col-span-6 col-span-full"
                                              >
                                                  <button
                                                          @click="addReceiptIsActive = false; addNewReceiptIsActive = false"
                                                          type="submit"
                                                          class="flex justify-center items-center bg-white hover:bg-gray-100 border border-gray-300 text-gray-700 font-semibold w-full rounded-lg py-2.5 px-4"
                                                  >
                                                      <span>بازگشت به رسیدهای ثبت‌شده</span>
                                                  </button>
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                               </div>
                           </div>
                            <div class="lg:col-span-6 col-span-full">
                                <div class="lg:bg-[#F8FAFC] h-full lg:py-5 lg:px-8">
                                    <div class="lg:flex hidden items-center justify-center gap-2 bg-[#EAEFFF] rounded-lg border border-[#D9E2FF]  text-primary-700 font-semibold py-2.5 md:px-6 px-4 mb-6">
                                        <div class="flex items-center gap-2 text-sm">
                                            <div class="w-6">
                                                <img class="w-full"
                                                     src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/timer.svg">
                                            </div>
                                            <span>زمان باقی‌مانده</span>
                                        </div>
                                        <div x-show="time.textTime">
                                            <div class="flex text-center">
                                                <template
                                                        x-for="(char) in time.textTimeSeconds.split('').map(Number).reverse()">
                                                    <span x-text="char" class="w-3"></span>
                                                </template>
                                                :
                                                <template
                                                        x-for="char in time.textTimeMinutes.split('').map(Number).reverse()">
                                                    <span x-text="char" class="w-3"></span>
                                                </template>
                                                :
                                                <template
                                                        x-for="(char) in time.textTimeHours.split('').map(Number).reverse()">
                                                    <span x-text="char" class="w-3"></span>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="lg:block hidden font-bold mb-3"> شماره کارت یا شبا مقصد</div>
                                        <div class="lg:static absolute top-[62px] left-0 w-full">
                                            <div class="relative z-10 lg:h-[180px] h-[172px] lg:rounded-[20px] rounded-xl overflow-hidden lg:p-5 p-4 mb-6">

                                                <!-- background -->
                                                <div class="absolute top-0 left-0 w-full h-full -z-10"
                                                     style="background: linear-gradient(103.45deg, #003F82 23.41%, #0058B5 98.12%);">
                                                    <img class="md:block hidden w-full h-full object-cover"
                                                         src="<?php echo GATELAND_URL . 'assets'; ?>/images/card-mask.png">
                                                </div>

                                                <div class="flex flex-col h-full text-white">
                                                    <div class="flex items-center mb-3">
                                                        <div class="lg:text-lg text-sm lg:font-medium font-bold">
                                                            <span x-text="pageDetails?.card.name"></span>
                                                        </div>
                                                        <div class="lg:block hidden md:h-8 h-7 mr-auto">
                                                            <img :src="pageDetails?.bank.logo" class="h-full">
                                                        </div>
                                                    </div>
                                                    <div class="mt-auto">
                                                        <!--<div class="md:text-xl text-sm md:mb-2 mb-1">
															اطلاعات مقصد:
															<span x-text="pageDetails?.card.name"></span>
														</div>-->
                                                        <template x-if="pageDetails?.card.number">
                                                            <div class="lg:mb-3 mb-1">
                                                                <div class="lg:hidden text-[#D1E9FF] text-sm font-light mb-0.5">شماره کارت مقصد</div>
                                                                <div class="flex items-center gap-1 text-white">
                                                                    <div dir="ltr" class="xl:text-lg font-normal font-cousine">
                                                                        <span x-text="gatelandFormatCardNumber(pageDetails?.card.number)"></span>
                                                                    </div>
                                                                    <div class="mr-auto">
                                                                        <button
                                                                                @click="gatelandCopyToClipboard($el, 'شماره کارت')"
                                                                                :data-copy="pageDetails?.card.number"
                                                                                class="flex items-center justify-center gap-1.5 bg-white bg-opacity-20 rounded-lg border border-primary-100 hover:bg-opacity-30 px-2.5 py-1.5"
                                                                        >
                                                                            <img class="lg:block hidden w-[18px] min-w-[18px]" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/copy-white.svg">
                                                                            <span class="text-copy text-xs">کپی</span>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </template>
                                                        <template x-if="pageDetails?.card.iban">
                                                            <div>
                                                                <div class="lg:hidden text-[#D1E9FF] text-sm font-light mb-0.5">شماره شبا مقصد</div>
                                                                <div class="flex items-center gap-1 text-white">
                                                                    <div dir="ltr" class="xl:text-lg text-sm font-normal font-cousine">
                                                                        <span x-text="'IR' +gatelandFormatIban(pageDetails?.card.iban)"></span>
                                                                    </div>
                                                                    <div class="mr-auto">
                                                                        <button
                                                                                @click="gatelandCopyToClipboard($el, 'شماره کارت')"
                                                                                :data-copy="pageDetails?.card.iban"
                                                                                class="flex items-center justify-center gap-1.5 bg-white bg-opacity-20 rounded-lg border border-primary-100 hover:bg-opacity-30 px-2.5 py-1.5"
                                                                        >
                                                                            <img class="lg:block hidden w-[18px] min-w-[18px]" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/copy-white.svg">
                                                                            <span class="text-copy text-xs">کپی</span>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-white border border-[#EAECF0] text-sm rounded-2xl p-4">
                                        <div class="flex items-center gap-2 mb-6">
                                            <img class="min-w-fit" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/info.svg">
                                            <div class="font-semibold">
                                                راهنمای پرداخت کارت به کارت
                                            </div>
                                        </div>
                                        <ul class="list-disc space-y-4 pr-6">
                                            <li>رسید شما حداکثر تا 24 ساعت کاری پس از ثبت بررسی می‌شود.</li>
                                            <li>لطفا به مبلغ درج شده دقت کنید و آن را عینا و بدون رند کردن واریز نمایید.</li>
                                            <li>رسید خود را پیش از پایان زمان باقی‌مانده ثبت کنید.</li>
                                            <li>اگر در چند مرحله واریز می‌کنید، می‌توانید چند رسید ثبت کنید.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:bg-white text-center text-[#7A8696] lg:text-sm text-xs lg:border border-[#E6E9F0] lg:rounded-b-3xl md:rounded-t-none py-5 lg:px-8 px-3 lg:mb-0 mb-2">
                        قدرت‌گرفته از گیت‌لند
                    </div>
                </div>
            </section>
        </div>
    </section>

    <section
            x-show="!addReceiptIsActive"
            x-transition
            class="bg-[#EEF1F7] text-[#344054]"
    >
        <div class="container">
            <section class="min-h-screen flex items-center justify-center hidden">
                <div class="w-[600px] max-w-full lg:bg-white lg:shadow-[0_28.63px_71.56px_-40.55px_#10162266] lg:rounded-3xl lg:border border-[#E6E9F0] overflow-hidden lg:my-6 my-3 mx-auto">
                    <div class="bg-white flex items-center md:gap-3 gap-2 lg:border-b lg:border-x-0 lg:border-t-0 border border-[#E6E9F0] lg:rounded-t-3xl lg:rounded-b-none rounded-xl lg:py-5 py-3 lg:px-8 px-3 lg:mb-0 mb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="md:size-14 size-10 skeleton md:rounded-[10px] rounded-md"></div>
                            <div>
                                <div class="md:w-28 w-20 md:h-[28px] h-5 skeleton rounded-md"></div>
                                <div class="md:w-20 w-16 md:h-5 h-4 skeleton rounded-md mt-1"></div>
                            </div>
                        </div>
                        <div class="md:w-28 w-16 md:h-11 h-5 rounded-md skeleton mr-auto"></div>
                    </div>
                    <div class="bg-white flex flex-col items-center text-center lg:border-0 border border-[#E6E9F0] lg:rounded-none rounded-2xl p-4 lg:mb-2 mb-3">
                        <div class="lg:size-14 size-12 skeleton rounded-full mb-2 mx-auto"></div>
                        <div class="h-6 w-28 max-w-full skeleton rounded-full mb-3"></div>
                        <div class="h-5 w-5/6 skeleton rounded-full mb-2"></div>
                        <div class="h-6 w-44 max-w-full skeleton rounded-full"></div>
                    </div>
                    <div class="bg-white lg:border-0 border border-[#E6E9F0] lg:rounded-b-none rounded-xl lg:px-4 lg:pb-4">
                        <div class="lg:border border-[#E6E9F0] rounded-2xl p-3">
                            <div class="h-6 w-32 skeleton max-w-full rounded-lg mb-4"></div>
                            <div class="space-y-2 mb-4">
                               <template x-for="item in [1, 2]">
                                   <div class="flex gap-2 bg-white border border-[#E6E9F0] rounded-xl p-3">
                                       <div class="size-10 min-w-10 skeleton rounded-lg"></div>
                                       <div class="w-full">
                                           <div class="h-5 w-20 skeleton rounded-full mb-1"></div>
                                           <div class="h-4 w-32 skeleton rounded-full"></div>
                                       </div>
                                   </div>
                               </template>
                            </div>
                            <div class="h-10 skeleton rounded-lg mb-3"></div>
                            <div>
                                <div class="h-3 skeleton rounded-full mb-0.5"></div>
                                <div class="h-3 w-2/3 skeleton rounded-full"></div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center lg:bg-[#F8FAFC] text-[#7A8696] lg:text-sm text-xs lg:border-t border-[#E6E9F0] lg:rounded-b-3xl md:rounded-t-none py-5 lg:px-8 px-3 lg:mb-0 mb-2">
                        قدرت‌گرفته از گیت‌لند
                    </div>
                </div>
            </section>

            <section class="min-h-screen flex items-center justify-center">
                <div class="w-[600px] max-w-full lg:bg-white lg:shadow-[0_28.63px_71.56px_-40.55px_#10162266] lg:rounded-3xl lg:border border-[#E6E9F0] overflow-hidden lg:my-6 my-3 mx-auto">
                    <div class="bg-white flex items-center md:gap-3 gap-2 lg:border-b lg:border-x-0 lg:border-t-0 border border-[#E6E9F0] lg:rounded-t-3xl lg:rounded-b-none rounded-xl lg:py-5 py-3 lg:px-4 px-3 lg:mb-0 mb-3">
                        <div class="flex items-center gap-2.5">
                            <template x-if="pageDetails?.site.logo">
                                <a :href="pageDetails?.site.url" class="md:size-14 size-10 overflow-hidden md:rounded-[10px] rounded-md">
                                    <img  class="w-full h-full object-cover" :src="pageDetails.site.logo">
                                </a>
                            </template>
                            <template x-if="!pageDetails?.site.logo">
                                <a :href="pageDetails?.site.url" class="md:size-14 size-10 flex items-center justify-center bg-[linear-gradient(130.24deg,#FF7A45_0%,#FF4D6D_99.96%)] md:rounded-[10px] rounded-md">
                                    <span class="md:text-[28px] text-base text-white"  x-text="pageDetails?.site.name ? pageDetails?.site.name.charAt(0) : ''"></span>
                                </a>
                            </template>
                            <div>
                                <a :href="pageDetails?.site.url" class="font-bold md:text-lg text-sm">
                                    <span x-text="pageDetails?.site.name"></span>
                                </a>
                                <div class="flex items-center gap-1 md:mt-0 -mt-0.5">
                                    <div class="size-1.5 rounded-full bg-success-500"></div>
                                    <span class="md:text-sm text-xs">کارت به کارت</span>
                                </div>
                            </div>
                        </div>
                        <div class="sm:bg-[#F8FAFC] sm:border border-[#E6E9F0] flex items-center gap-1 sm:rounded-xl sm:py-2.5 sm:px-4 mr-auto">
                            <span class="sm:block hidden text-[#7A8696]">سفارش</span>
                            <span class="text-[#3A4658] font-semibold md:text-base text-sm" dir="ltr" x-text="'#' + pageDetails?.order_id"></span>
                        </div>
                    </div>
                    <div x-show="addNewReceiptIsActive" class="bg-white flex flex-col items-center text-center lg:border-0 border border-[#E6E9F0] lg:rounded-none rounded-2xl p-4">
                        <div class="lg:size-14 size-12 bg-[#FFF4E0] rounded-full flex items-center justify-center mb-2 mx-auto">
                            <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/clock-warning.svg">
                        </div>
                        <div class="font-extrabold text-[#0F172A] mb-3">رسید شما ثبت شد</div>
                        <div class="text-[#475569] font-normal text-xs mb-2">
                            رسید شما در انتظار بررسی است.پس از تایید رسیدها و تکمیل مبلغ سفارش، سفارش شما به‌صورت خودکار تکمیل می‌شود.
                        </div>
                        <div class="flex items-center gap-2 bg-[#EFF2F7] rounded-full text-xs py-1 pr-2 pl-3">
                            <div class="blink size-4 border-2 border-[#D98A00] border-opacity-30 rounded-full flex items-center justify-center">
                                <div class="size-1.5 bg-[#D98A00] rounded-full"></div>
                            </div>
                            <span> وضعیت به‌صورت خودکار به‌روزرسانی می‌شود</span>
                        </div>
                    </div>

                    <!-- skeleton -->
                    <div x-show="tableLoaderIsActive">
                        <div class="lg:bg-white lg:rounded-b-none lg:px-4 lg:pb-4 mt-3">
                            <div class="bg-white flex border border-[#E6E9F0] rounded-xl mb-3">
                                <div class="w-1/2 md:text-sm text-xs border-l border-[#E6E9F0] p-2.5">
                                    <div class="lg:h-5 h-4 w-32 max-w-full skeleton rounded-full mb-1"></div>
                                    <div class="lg:h-5 h-4 w-44 max-w-full skeleton rounded-full"></div>
                                </div>
                                <div class="w-1/2 md:text-sm text-xs p-2.5">
                                    <div class="lg:h-5 h-4 w-32 max-w-full skeleton rounded-full mb-1"></div>
                                    <div class="lg:h-5 h-4 w-44 max-w-full skeleton rounded-full"></div>
                                </div>
                            </div>
                            <div class="bg-white border border-[#E6E9F0] rounded-xl p-3">
                                <div class="h-6 w-32 skeleton max-w-full rounded-lg mb-4"></div>
                                <div class="space-y-2 mb-4">
                                    <template x-for="item in [1, 2]">
                                        <div class="flex gap-2 bg-white border border-[#E6E9F0] rounded-xl p-3">
                                            <div class="size-10 min-w-10 skeleton rounded-lg"></div>
                                            <div class="w-full">
                                                <div class="h-5 w-20 skeleton rounded-full mb-1"></div>
                                                <div class="h-4 w-32 skeleton rounded-full"></div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                <div class="h-10 skeleton rounded-lg mb-3"></div>
                                <div>
                                    <div class="h-3 skeleton rounded-full mb-0.5"></div>
                                    <div class="h-3 w-2/3 skeleton rounded-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="!tableLoaderIsActive">
                        <div class="lg:bg-white lg:rounded-b-none lg:px-4 lg:pb-4 mt-3">
                            <div class="bg-white flex border border-[#E6E9F0] rounded-xl mb-3">
                                <div class="w-1/2 md:text-sm text-xs border-l border-[#E6E9F0] p-2.5">
                                    <div class="text-[#94A3B8] mb-1">مبلغ سفارش</div>
                                    <div class="font-bold" x-text="`${gatelandFormatPrice(pageDetails?.amount)} ${pageDetails?.currency}`"></div>
                                </div>
                                <div class="w-1/2 md:text-sm text-xs p-2.5">
                                    <div class="text-[#94A3B8] mb-1">مجموع مبلغ تاییدشده</div>
                                    <div class="font-bold" x-text="`${gatelandFormatPrice(pageDetails?.total_accepted_amount)} ${pageDetails?.currency}`"></div>
                                </div>
                            </div>
                            <div class="bg-white border border-[#E6E9F0] rounded-xl p-3">
                                <div class="flex items-center gap-1 text-sm mb-4">
                                    <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/receipt-gray-2.svg">
                                    <div class="text-gray-500 font-bold">
                                        رسیدهای ثبت‌شده
                                        (<span x-text="tableData.length"></span>)
                                    </div>
                                </div>

                                <div
                                        x-show="tableData.length < 1"
                                        class="flex flex-col items-center justify-center text-center p-4"
                                >
                                    <div class="mb-3">
                                        <div class="size-12 flex items-center justify-center bg-primary-50 rounded-full mx-auto">
                                            <div class="size-9 flex items-center justify-center bg-primary-100 rounded-full">
                                                <img class="size-5"
                                                     src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/search-blue.svg">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="font-semibold text-gray- text-xs mb-1">
                                        رسیدی یافت نشد.
                                    </div>
                                </div>

                                <div
                                        x-show="tableData.length >= 1"
                                        class="space-y-3 mb-4"
                                >
                                    <template x-for="row in tableData">
                                        <div class="flex gap-2 bg-white border border-[#E6E9F0] rounded-xl !leading-none p-3">

                                            <div>
                                                <div
                                                        @click="openViewModal(row)"
                                                        x-show="row.attachment_url"
                                                        class="size-10 min-w-10 flex items-center justify-center border border-[#E6E9F0] rounded-lg overflow-hidden  cursor-pointer p-0.5"
                                                >
                                                    <img class="max-w-full rounded" :src="row.attachment_url">
                                                </div>
                                                <div >
                                                    <div
                                                            x-show="!row.attachment_url"
                                                            class="size-10 min-w-10 flex items-center justify-center bg-[#F1F4FA] rounded-lg"
                                                    >
                                                        <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/bookmark_gray.svg">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="w-full">
                                                <div class="flex items-start gap-1 mb-1">
                                                    <div class="text-[#0F172A] text-sm">
                                                        <template x-if="row.card_number.length === 16">
                                                            <span dir="ltr" x-text="gatelandFormatCardNumber(row.card_number)"></span>
                                                        </template>
                                                        <template x-if="row.card_number.length > 16">
                                                            <span dir="ltr" x-text="'IR' + gatelandFormatIban(row.card_number)"></span>
                                                        </template>
                                                    </div>
                                                    <template x-if="row.status === 'accepted'">
                                                        <div class="inline-block rounded-full bg-success-50 text-[10px] text-nowrap text-success-700 px-2 py-1 mr-auto">
                                                            تایید شده
                                                        </div>
                                                    </template>
                                                    <template x-if="row.status === 'rejected'">
                                                        <div class="inline-block rounded-full bg-error-50 text-[10px] text-nowrap text-error-700 px-2 py-1 mr-auto">
                                                            رد شده
                                                        </div>
                                                    </template>
                                                    <template x-if="row.status === 'pending'">
                                                        <div class="inline-block rounded-full bg-blue-50 text-[10px] text-nowrap text-blue-700 px-2 py-1 mr-auto">
                                                            نیازمند بررسی
                                                        </div>
                                                    </template>
                                                </div>
                                                <div class="flex gap-1 text-xs">
                                                    <div class="text-[#94A3B8] text-[11px]">
                                                        <span x-text="row.created_at"></span>
                                                        ·
                                                        شناسه رسید:
                                                        <span x-text="row.id"></span>
                                                    </div>
                                                    <button
                                                            x-show="row.status === 'pending'"
                                                            @click="openDeleteModal(row)"
                                                            class="flex gap-1 text-error-700 hover:bg-error-100 rounded-sm size-4 p-0.5 mr-auto"
                                                    >
                                                        <img class="w-full" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/trash-red.svg">
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div>
                                    <button
                                            @click="addReceiptIsActive = true"
                                            class="flex items-center justify-center w-full gap-1.5 bg-[#EAEFFF] text-[#1E40C4] text-xs rounded-lg p-3 disabled:opacity-50 mb-3"
                                            :disabled="tableData.length === pageDetails?.max_receipt_count"
                                    >
                                        <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/add.svg">
                                        <span class="font-bold">ثبت رسید جدید</span>
                                    </button>
                                </div>

                                <div
                                        x-show="tableData.length === pageDetails?.max_receipt_count"
                                        class="flex gap-2 items-start border border-primary-300 bg-primary-25 rounded-lg text-sm p-3 mb-3"
                                >
                                    <img class="w-4 min-w-4" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/info-square.svg">
                                    <div class="font-normal text-primary-700 pr">
                                        حداکثر رسید قابل ثبت برای این تراکنش
                                        <span x-text="pageDetails?.max_receipt_count + ' رسید'" class="font-semibold"></span>
                                        می‌باشد.
                                    </div>
                                </div>

                                <div class="text-[#94A3B8] text-xs font-normal">
                                    اگر مبلغ را اشتباه واریز کردید یا رسید قبلی رد شد، می‌توانید رسید دیگری ثبت کنید.
                                    در صورتی که مبلغ واریزی شما از مبلغ تراکنش بیشتر است، با پشتیبانی سایت تماس بگیرید.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center lg:bg-[#F8FAFC] text-[#7A8696] lg:text-sm text-xs lg:border-t border-[#E6E9F0] lg:rounded-b-3xl md:rounded-t-none py-5 lg:px-8 px-3 lg:mb-0 mb-2">
                        قدرت‌گرفته از گیت‌لند
                    </div>
                </div>
            </section>
        </div>
    </section>

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
                <div class="text-sm text-gray-600">
                    شما در حال مشاهده رسید
                    <span x-text="modals.view.receipt?.id"></span>
                    هستید.
                </div>
            </div>
            <div class="bg-[#F1F5F9] bg-opacity-50 p-5">
                <img class="w-full rounded" :src="modals.view.receipt?.attachment_url">
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

    <!-- delete receipt modal -->
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
                حذف رسید
            </div>
            <div class="text-sm text-gray-600 mb-6">
                شما در حال حذف رسید
                <span x-text="modals.delete.receipt?.id" class="font-semibold"></span>
                هستید. پس از تأیید، این رسید دیگر توسط تیم ما بررسی نخواهد شد و امکان بازگردانی آن وجود ندارد.
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
                <button @click="deleteReceipt()"
                        class="w-1/2 border bg-error-600 border-error-600 text-white font-semibold rounded-lg hover:shadow p-2">
                    حذف رسید
                </button>
            </div>
        </div>
    </div>

</section>

<script src="<?php echo esc_url( GATELAND_URL ) . '/assets/js/global.js' ?>"></script>
</body>
</html>