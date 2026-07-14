import { useState } from 'react';

/**
 * A small "?" button that fetches and shows an editable help message
 * (stored in the help_messages table, looked up by messageKey) in a popup.
 */
export default function HelpTip({ messageKey }) {
    const [open, setOpen] = useState(false);
    const [message, setMessage] = useState(null);
    const [loading, setLoading] = useState(false);

    const openTip = async () => {
        setOpen(true);
        if (message || loading) return;

        setLoading(true);
        try {
            const response = await fetch(route('help-messages.show', messageKey));
            const data = await response.json();
            setMessage(data);
        } catch {
            setMessage({ title: null, body: 'Could not load help right now.' });
        } finally {
            setLoading(false);
        }
    };

    return (
        <>
            <button
                type="button"
                onClick={openTip}
                aria-label="Help"
                className="w-5 h-5 inline-flex items-center justify-center rounded-full border border-gray-400 text-gray-500 text-xs font-medium hover:bg-gray-100 flex-shrink-0"
            >
                ?
            </button>

            {open && (
                <div
                    className="fixed inset-0 z-[1100] flex items-center justify-center bg-black/40 p-4"
                    onClick={() => setOpen(false)}
                >
                    <div
                        className="bg-white rounded-lg shadow-lg max-w-sm w-full p-5"
                        onClick={(e) => e.stopPropagation()}
                    >
                        {loading ? (
                            <p className="text-sm text-gray-500">Loading…</p>
                        ) : (
                            <>
                                {message?.title && (
                                    <h3 className="text-base font-semibold text-gray-900 mb-2">{message.title}</h3>
                                )}
                                <p className="text-sm text-gray-600 whitespace-pre-line">{message?.body}</p>
                            </>
                        )}
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="mt-4 w-full py-2 bg-green-600 text-white rounded-lg text-sm font-medium"
                        >
                            Got it
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}
