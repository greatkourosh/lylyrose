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

	<section class="bg-[#F9FAFB] text-base text-gray-900 py-6 md:pl-5 pl-2.5">

		<div class="container">
			<div class="grid grid-cols-12 md:gap-x-8 gap-y-6 mb-12">
				<div class="lg:col-span-6 col-span-full">
					<div class="h-full bg-white border border-[#EAECF0] rounded-2xl md:p-6 p-5">

						<div x-show="pageLoaderIsActive">
							<div class="skeleton md:h-52 h-40 rounded-[20px] mb-6"></div>
							<div>
								<template x-for="item in [1, 2, 3]">
									<div class="mb-4">
										<div class="skeleton h-5 w-32 rounded-full mb-1.5"></div>
										<div class="skeleton h-12 rounded-lg"></div>
									</div>
								</template>
								<div class="mb-4">
									<div class="skeleton h-5 w-32 rounded-full mb-1.5"></div>
									<div class="skeleton h-[70px] rounded-lg"></div>
								</div>
								<div class="skeleton h-12 rounded-lg"></div>
							</div>
						</div>

						<div x-show="!pageLoaderIsActive">
							<div class="relative z-10 md:h-52 h-40 rounded-[20px] overflow-hidden md:p-8 p-5 mb-6">

								<!-- background -->
								<div class="absolute top-0 left-0 w-full h-full -z-10"
								     style="background: linear-gradient(103.45deg, #003F82 23.41%, #0058B5 98.12%);">
									<img class="w-full h-full object-cover"
									     src="<?php echo GATELAND_URL . 'assets'; ?>/images/card-mask.png">
								</div>

								<div class="flex flex-col h-full text-white">
									<div class="flex items-center">
										<div class="md:text-xl text-sm">
											<span x-text="pageDetails?.bank.name"></span>
										</div>
										<div class="md:h-12 h-8 mr-auto">
											<img :src="pageDetails?.bank.logo" class="h-full">
										</div>
									</div>
									<div class="mt-auto">
										<div class="md:text-xl text-sm md:mb-2 mb-1">
											اطلاعات مقصد:
											<span x-text="pageDetails?.card.name"></span>
										</div>
										<div class="flex items-center gap-1 text-white">
											<div dir="ltr" class="xl:text- lg:text-2xl sm:text-xl text-lg font-bold">
												<span x-text="gatelandFormatCardNumber(pageDetails?.card.number)"></span>
											</div>
											<div class="mr-auto">
												<button
														@click="gatelandCopyToClipboard($el, 'شماره کارت')"
														:data-copy="pageDetails?.card.number"
														class="md:size-9 size-8 flex items-center justify-center rounded-full bg-white bg-opacity-0 hover:bg-opacity-20 p-2"
												>
													<svg class="w-full" viewBox="0 0 20 20" fill="none"
													     xmlns="http://www.w3.org/2000/svg">
														<path d="M4.45508 12.8079C3.67851 12.8079 3.29022 12.8079 2.98394 12.6811C2.57556 12.5119 2.2511 12.1875 2.08195 11.7791C1.95508 11.4728 1.95508 11.0845 1.95508 10.3079V4.64128C1.95508 3.70786 1.95508 3.24114 2.13673 2.88463C2.29652 2.57102 2.55149 2.31605 2.86509 2.15627C3.22161 1.97461 3.68832 1.97461 4.62174 1.97461H10.2884C11.065 1.97461 11.4533 1.97461 11.7596 2.10148C12.1679 2.27063 12.4924 2.59509 12.6615 3.00347C12.7884 3.30976 12.7884 3.69804 12.7884 4.47461M10.4551 18.6413H15.9551C16.8885 18.6413 17.3552 18.6413 17.7117 18.4596C18.0253 18.2998 18.2803 18.0449 18.4401 17.7313C18.6217 17.3747 18.6217 16.908 18.6217 15.9746V10.4746C18.6217 9.54119 18.6217 9.07448 18.4401 8.71796C18.2803 8.40436 18.0253 8.14939 17.7117 7.9896C17.3552 7.80794 16.8885 7.80794 15.9551 7.80794H10.4551C9.52166 7.80794 9.05495 7.80794 8.69843 7.9896C8.38482 8.14939 8.12986 8.40436 7.97007 8.71796C7.78841 9.07448 7.78841 9.54119 7.78841 10.4746V15.9746C7.78841 16.908 7.78841 17.3747 7.97007 17.7313C8.12986 18.0449 8.38482 18.2998 8.69843 18.4596C9.05495 18.6413 9.52166 18.6413 10.4551 18.6413Z"
														      stroke="white" stroke-width="2" stroke-linecap="round"
														      stroke-linejoin="round"/>
													</svg>
												</button>
											</div>
										</div>
									</div>
								</div>

							</div>

							<div>
								<div class="mb-6">
									<div class="mb-4">
										<label class="block text-sm text-gray-700 mb-1.5">
											شماره کارت یا شبا خود را وارد کنید
										</label>
										<input
												x-model="inputs.cardNumber.value"
												@input="inputs.cardNumber.value = gatelandFormatCardNumber(inputs.cardNumber.value, true)"
												maxlength="29"
												minlength="29"
												type="text"
												placeholder="شماره کارت یا شبایی که از آن پرداخت را انجام داده‌اید"
												class="block border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-lg text-gray-500 w-full py-2.5 px-3"
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
											شماره پیگیری
										</label>
										<input
												x-model="inputs.trackingNumber.value"
												type="number"
												placeholder="شماره پیگیری یا شماره مرجع تراکنش را وارد کنید"
												class="block border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-lg text-gray-500 w-full py-2.5 px-3"
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
											مبلغ تراکنش
											<span x-text="pageDetails?.currency ? `(${pageDetails?.currency})` : ''"></span>
										</label>
										<input
												x-model="inputs.amount.value"
												@input="$el.value = gatelandFormatPrice($el.value)"
												placeholder="مبلغ تراکنش واریز شده را وارد کنید"
												class="block border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-lg text-gray-500 w-full py-2.5 px-3"
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
											رسید واریز
										</label>
										<div
												@click="$refs.inputFile.click()"
												class="border border-gray-300 shadow-[0_1px_2px_0_#1018280D] flex items-center gap-3 cursor-pointer hover:bg-gray-100 py-2.5 px-3 rounded-lg"
										>
											<div class="size-12 flex items-center justify-center bg-gray-50 rounded-full">
												<div class="size-9 flex items-center justify-center bg-gray-100 rounded-full">
													<img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/upload-cloud.svg">
												</div>
											</div>
											<template x-if="!inputs.receiptImage.fileName">
												<div class="text-gray-600 mb-1">
													<div class="text-sm font-semibold">برای آپلود کلیک کنید.</div>
													<div class="">
														فرمت‌های قابل قبول: png ،jpg ،jpeg |
														حداکثر
														<span x-text="pageDetails?.max_file_size"></span>
														مگابایت
													</div>
												</div>
											</template>
											<template x-if="inputs.receiptImage.fileName">
												<div x-text="inputs.receiptImage.fileName"
												     class="max-w-full line-clamp-1 text-lg text-primary-600"></div>
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
								<div class="flex gap-4">
									<button
											@click="uploadReceipt()"
											type="submit"
											class="flex justify-center items-center bg-primary-500 hover:bg-primary-600 text-white font-semibold w-full rounded-lg py-2.5 px-4"
									>
										<span x-show="!uploadLoaderIsActive">ارسال اطلاعات پرداخت</span>
										<span
												x-show="uploadLoaderIsActive"
												class="rotation-animation size-6"
										>
                                                <img class="h-full"
                                                     src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/refresh-white.svg">
                                            </span>
									</button>
								</div>
							</div>
						</div>

					</div>
				</div>
				<div class="lg:col-span-6 col-span-full">
					<div class="h-full flex flex-col lg:gap-y-8 gap-y-6">
						<div class="bg-white border border-[#EAECF0] rounded-2xl md:p-8 p-5">

							<!-- skeleton -->
							<div x-show="tableLoaderIsActive">
								<div class="skeleton md:size-[70px] size-12 rounded-lg"></div>
								<div class="md:space-y-6 space-y-4">
									<template x-for="item in [1, 2, 3, 4]">
										<div class="flex items-center gap-2">
											<div class="flex items-center gap-2 text-nowrap font-medium">
												<div class="size-6 skeleton rounded-md"></div>
												<div class="w-32 h-6 skeleton rounded-md"></div>
											</div>
											<div class="w-24 h-6 skeleton rounded-md mr-auto"></div>
										</div>
									</template>
								</div>
							</div>

							<div x-show="!tableLoaderIsActive">
								<div class="flex items-center gap-4 md:mb-8 mb-6">
									<div class="md:size-[70px] size-12 flex items-center justify-center bg-primary-600 rounded-lg p-2">
										<img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/info-square-white.svg">
									</div>
									<div class="text-xl font-bold text-gray-800">
										اطلاعات
									</div>
								</div>
								<div class="text-sm text-gray-900 md:space-y-6 space-y-4">
									<div class="flex items-center gap-2">
										<div class="flex items-center gap-2 text-nowrap font-medium">
											<div class="w-6 min-w-6">
												<img class="w-full"
												     src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/money-gray.svg">
											</div>
											<span>مبلغ سفارش</span>
										</div>
										<div class="mr-auto text-left">
											<span x-text="gatelandFormatPrice(pageDetails?.amount)"></span> <span
													x-text="pageDetails?.currency"></span>
										</div>
									</div>
									<div class="flex items-center gap-2">
										<div class="flex items-center gap-2 text-nowrap font-medium">
											<div class="w-6 min-w-6">
												<img class="w-full"
												     src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/bookmark-gray.svg">
											</div>
											<span>شماره سفارش</span>
										</div>
										<div class="mr-auto text-left">
											<span x-text="pageDetails?.order_id"></span>
										</div>
									</div>
									<div class="flex items-center gap-2">
										<div class="flex items-center gap-2 text-nowrap font-medium">
											<div class="w-6 min-w-6">
												<img class="w-full"
												     src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/info-square-gray.svg">
											</div>
											<span>پذیرنده</span>
										</div>
										<div class="mr-auto text-left">
											<span x-text="pageDetails?.site_name"></span>
										</div>
									</div>
									<div class="flex items-center gap-2">
										<div class="flex items-center gap-2 text-nowrap font-medium">
											<div class="w-6 min-w-6">
												<img class="w-full"
												     src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/calendar-gray.svg">
											</div>
											<span>تاریخ و زمان سفارش</span>
										</div>
										<div class="mr-auto text-left">
											<span x-text="pageDetails?.created_at"></span>
										</div>
									</div>
									<div class="flex items-center gap-2">
										<div class="flex items-center gap-2 text-nowrap font-medium">
											<div class="w-6 min-w-6">
												<img class="w-full"
												     src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/receipt-text.svg">
											</div>
											<span>شماره تراکنش</span>
										</div>
										<div class="mr-auto text-left">
											<span x-text="pageDetails?.id"></span>
										</div>
									</div>
								</div>
							</div>

						</div>
						<div class="bg-white border border-[#EAECF0] rounded-2xl md:p-8 p-5 mt-auto">
							<div x-show="pageLoaderIsActive">
								<div class="skeleton md:size-[70px] size-12 rounded-lg"></div>
								<div class="md:space-y-4 space-y-3 mb-7">
									<template x-for="item in [1, 2, 3]">
										<div class="skeleton h-5 rounded-md"></div>
									</template>
								</div>
								<div class="skeleton h-11 rounded-md"></div>
							</div>
							<div x-show="!pageLoaderIsActive">
								<div class="flex items-center gap-4 md:mb-8 mb-6">
									<div class="md:size-[70px] size-12 flex items-center justify-center bg-primary-600 rounded-lg">
										<img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/security-user.svg">
									</div>
									<div class="text-xl font-bold text-gray-800">
										قوانین و شرایط
									</div>
								</div>
								<div class="text-sm text-gray-900 mb-7">
									<ul class="list-disc md:space-y-4 space-y-3 pr-5">
										<li>
											رسید واریز شما حداکثر تا ۷۲ ساعت بعد از ثبت آن، بررسی خواهد شد.
										</li>
										<li>
											لطفا به مبلغ درج شده دقت کنید و آن را عینا و بدون رند کردن واریز نمایید.
										</li>
										<li>
											لطفا اطلاعات واریز خود را در مدت زمان تعیین شده ارسال نمایید.
										</li>
									</ul>
								</div>
								<div class="bg-primary-100 flex items-center gap-2 rounded-lg py-2.5 md:px-6 px-4">
									<div class="flex items-center gap-2 text-sm text-primary-800">
										<div class="w-6">
											<img class="w-full"
											     src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/clock-stopwatch.svg">
										</div>
										<span>زمان باقی‌مانده</span>
									</div>
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
							</div>
						</div>
					</div>
				</div>
			</div>

			<!--table-->
			<div class="bg-white border border-gray-300 rounded-xl overflow-hidden mb-5">

				<div class="flex items-center flex-wrap gap-3 p-4">
					<div class="text-lg font-semibold order-first ml-auto">
						لیست رسید‌های کارت به کارت
					</div>
				</div>

				<div class="overflow-auto custom-scrollbar">
					<table class="w-full">
						<thead class="text-sm text-gray-600 text-nowrap">
						<tr>
							<td class="bg-gray-100 py-3 px-5">
								تصویر رسید
							</td>
							<td class="bg-gray-100 py-3 px-5">
								شناسه رسید
							</td>
							<td class="bg-gray-100 py-3 px-5">
								شماره پیگیری
							</td>
							<td class="bg-gray-100 py-3 px-5">
								مبلغ اظهار شده
							</td>
							<td class="bg-gray-100 py-3 px-5">
								مبلغ تایید شده
							</td>
							<td class="bg-gray-100 py-3 px-5">
								تاریخ ارسال
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
									<div class="skeleton w-16 h-9 rounded"></div>
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
									<div class="flex justify-center">
										<div class="skeleton w-28 h-9 rounded"></div>
									</div>
								</td>
							</tr>
						</template>
						</tbody>

						<tbody x-show="!tableLoaderIsActive && tableData.length > 0"
						       class="w-full text-sm text-gray-700">
						<template x-for="row in tableData">
							<tr class="border-b bg-white border-gray-200">
								<td class="py-4 md:px-5 px-3">
									<div
											@click="openViewModal(row)"
											class="flex items-center gap-2.5 cursor-pointer"
									>
										<div class="w-6 h-9 min-w-6 border rounded overflow-hidden">
											<img class="w-full h-full object-cover" :src="row.attachment_url">
										</div>
										<div class="min-w-6">
											<img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/expand.svg">
										</div>
									</div>
								</td>
								<td class="py-4 md:px-5 px-3">
									<span x-text="row.id"></span>
								</td>
								<td class="py-4 md:px-5 px-3">
									<span x-text="row.tracking_number"></span>
								</td>
								<td class="py-4 md:px-5 px-3">
									<span x-text="gatelandFormatPrice(row.amount)"></span>
									<span x-text="pageDetails?.currency"></span>
								</td>
								<td class="py-4 md:px-5 px-3">
									<span x-text="row.accepted_amount ? (gatelandFormatPrice(row.accepted_amount) + (pageDetails?.currency || '') ) : '-'"></span>
								</td>
								<td class="py-4 md:px-5 px-3">
									<span x-text="row.created_at"></span>
								</td>
								<td class="py-4 md:px-5 px-3">
									<template x-if="row.status === 'accepted'">
										<div class="inline-block rounded-full bg-success-50 text-xs text-nowrap text-success-700 px-2 py-1">
											تایید شده
										</div>
									</template>
									<template x-if="row.status === 'rejected'">
										<div class="inline-block rounded-full bg-error-50 text-xs text-nowrap text-error-700 px-2 py-1">
											رد شده
										</div>
									</template>
									<template x-if="row.status === 'pending'">
										<div class="inline-block rounded-full bg-blue-50 text-xs text-nowrap text-blue-700 px-2 py-1">
											نیازمند بررسی
										</div>
									</template>
								</td>
								<td class="py-4 md:px-5 px-3">
									<div class="flex justify-center min-w-40">
										<button
												:disabled="row.status !== 'pending'"
												@click="openDeleteModal(row)"
												class="inline-flex items-center gap-2 text-gray-700 border border-gray-300 text-sm text-nowrap font-semibold rounded-lg hover:bg-gray-100 disabled:opacity-60 disabled:cursor-default py-2.5 px-4"
										>
											<img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/trash-gray.svg">
											حذف رسید
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
								<img class="size-5"
								     src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/search-blue.svg">
							</div>
						</div>
					</div>
					<div class="font-semibold text-gray-900 mb-1">
						رسیدی یافت نشد.
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
		</div>

	</section>

</section>

<script src="<?php echo esc_url( GATELAND_URL ) . '/assets/js/global.js' ?>"></script>
</body>
</html>