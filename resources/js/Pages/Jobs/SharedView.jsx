import { Head, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { formatDate } from '@/dateInput';
import { pillBadgeClass } from '@/Utils/pillColors';

export default function SharedView({ job, logoUrl, viewingSupplier, canSubmitInvoice, quoteToken }) {
    const { flash, errors } = usePage().props;
    const fileInput = useRef(null);
    const [submitting, setSubmitting] = useState(false);
    const [invoiceForm, setInvoiceForm] = useState({
        name: job.name, description: '', amount: '', gst_inclusive: true,
    });

    const submitInvoice = () => {
        const formData = new FormData();
        formData.append('name', invoiceForm.name);
        formData.append('description', invoiceForm.description);
        formData.append('amount', invoiceForm.amount);
        formData.append('gst_inclusive', invoiceForm.gst_inclusive ? '1' : '0');
        if (fileInput.current.files[0]) {
            formData.append('invoice', fileInput.current.files[0]);
        }

        setSubmitting(true);
        router.post(route('quotes.share.expense', quoteToken), formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
            onSuccess: () => {
                setInvoiceForm({ name: job.name, description: '', amount: '', gst_inclusive: true });
                if (fileInput.current) fileInput.current.value = '';
            },
        });
    };

    return (
        <>
            <Head title={job.name} />

            <div className="min-h-screen bg-gray-100 flex justify-center p-4">
                <div className="max-w-lg w-full space-y-4 mt-8">
                    <div className="flex items-center justify-center gap-2 text-gray-400 text-sm mb-2">
                        <img src={logoUrl} className="w-5 h-5" alt="" />
                        <span>FieldWerkz</span>
                    </div>

                    {viewingSupplier && (
                        <div className="bg-green-50 border border-green-200 rounded-lg px-4 py-2 text-center">
                            <span className="text-sm text-green-800">
                                Viewing as <span className="font-medium">{viewingSupplier}</span>
                            </span>
                        </div>
                    )}

                    <div className="bg-white rounded-lg shadow p-4">
                        <h1 className="text-xl font-semibold text-gray-900 mb-3">{job.name}</h1>
                        <div className="flex flex-wrap gap-2">
                            {job.job_status && (
                                <span className={`text-xs px-2 py-1 rounded-full font-medium ${pillBadgeClass(job.job_status.color)}`}>
                                    {job.job_status.name}
                                </span>
                            )}
                            {job.priority && (
                                <span className={`text-xs px-2 py-1 rounded-full font-medium ${pillBadgeClass(job.priority.color)}`}>
                                    {job.priority.name}
                                </span>
                            )}
                            {job.job_type && (
                                <span className={`text-xs px-2 py-1 rounded-full font-medium ${pillBadgeClass(job.job_type.color)}`}>
                                    {job.job_type.name}
                                </span>
                            )}
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow p-4 space-y-3">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Details</h2>
                        {job.property && (
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Property</span>
                                <span className="text-sm text-gray-900">{job.property.name}</span>
                            </div>
                        )}
                        {job.scheduled_date && (
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Scheduled</span>
                                <span className="text-sm text-gray-900">
                                    {formatDate(job.scheduled_date.slice(0, 10), { weekday: 'short', year: false })}
                                </span>
                            </div>
                        )}
                        {job.description && (
                            <div>
                                <p className="text-sm text-gray-500 mb-1">Description</p>
                                <p className="text-sm text-gray-900 whitespace-pre-line">{job.description}</p>
                            </div>
                        )}
                    </div>

                    {job.photos.length > 0 && (
                        <div className="bg-white rounded-lg shadow p-4">
                            <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide mb-3">Photos</h2>
                            <div className="grid grid-cols-3 gap-2">
                                {job.photos.map((photo) => (
                                    <img
                                        key={photo.id}
                                        src={photo.url}
                                        className="w-full h-24 object-cover rounded-lg"
                                        alt=""
                                    />
                                ))}
                            </div>
                        </div>
                    )}

                    {canSubmitInvoice && (
                        <div className="bg-white rounded-lg shadow p-4 space-y-3">
                            <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Submit Invoice</h2>

                            {flash?.success && (
                                <p className="text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg p-2">
                                    {flash.success}
                                </p>
                            )}

                            <div>
                                <label className="block text-xs text-gray-500 mb-1">Name</label>
                                <input
                                    type="text"
                                    value={invoiceForm.name}
                                    onChange={(e) => setInvoiceForm({ ...invoiceForm, name: e.target.value })}
                                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                                />
                                {errors?.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                            </div>

                            <div>
                                <label className="block text-xs text-gray-500 mb-1">Description (optional)</label>
                                <textarea
                                    value={invoiceForm.description}
                                    onChange={(e) => setInvoiceForm({ ...invoiceForm, description: e.target.value })}
                                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                                    rows={2}
                                />
                            </div>

                            <div>
                                <label className="block text-xs text-gray-500 mb-1">Amount</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={invoiceForm.amount}
                                    onChange={(e) => setInvoiceForm({ ...invoiceForm, amount: e.target.value })}
                                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                                />
                                {errors?.amount && <p className="mt-1 text-xs text-red-600">{errors.amount}</p>}
                            </div>

                            <div className="flex gap-4">
                                <label className="flex items-center gap-1.5 text-sm text-gray-700">
                                    <input
                                        type="radio"
                                        name="gst_inclusive"
                                        checked={invoiceForm.gst_inclusive}
                                        onChange={() => setInvoiceForm({ ...invoiceForm, gst_inclusive: true })}
                                        className="text-green-600 focus:ring-green-500"
                                    />
                                    GST inclusive
                                </label>
                                <label className="flex items-center gap-1.5 text-sm text-gray-700">
                                    <input
                                        type="radio"
                                        name="gst_inclusive"
                                        checked={!invoiceForm.gst_inclusive}
                                        onChange={() => setInvoiceForm({ ...invoiceForm, gst_inclusive: false })}
                                        className="text-green-600 focus:ring-green-500"
                                    />
                                    GST exclusive
                                </label>
                            </div>

                            <div>
                                <label className="block text-xs text-gray-500 mb-1">Invoice (image or PDF)</label>
                                <input
                                    ref={fileInput}
                                    type="file"
                                    accept="image/*,application/pdf"
                                    className="w-full text-sm"
                                />
                                {errors?.invoice && <p className="mt-1 text-xs text-red-600">{errors.invoice}</p>}
                            </div>

                            <button
                                onClick={submitInvoice}
                                disabled={submitting || !invoiceForm.name || !invoiceForm.amount}
                                className="w-full py-2 bg-green-600 text-white rounded-lg text-sm font-medium disabled:opacity-50"
                            >
                                {submitting ? 'Submitting...' : 'Submit Invoice'}
                            </button>
                        </div>
                    )}

                    <p className="text-center text-xs text-gray-400 pt-2 pb-8">
                        Shared view — sign in to your FieldWerkz account to see more.
                    </p>
                </div>
            </div>
        </>
    );
}
