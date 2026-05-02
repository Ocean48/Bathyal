        </main> <!-- End main scrollable area -->
    </div> <!-- End main content wrapper -->

    <!-- Global App Modal (Alerts & Confirmations) -->
    <div id="global-modal-overlay" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-200">
        <div id="global-modal-box" class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 transform scale-95 transition-transform duration-200 overflow-hidden border border-slate-100">
            <div class="px-6 py-5 border-b border-slate-100/80 flex items-center space-x-3 bg-slate-50/50">
                <div id="global-modal-icon" class="w-10 h-10 rounded-full flex items-center justify-center shrink-0">
                    <!-- Icon injected by JS -->
                </div>
                <h3 id="global-modal-title" class="text-[17px] font-semibold text-slate-800 tracking-tight">Title</h3>
            </div>
            <div class="px-6 py-5">
                <p id="global-modal-message" class="text-sm text-slate-600 leading-relaxed">Message goes here...</p>
            </div>
            <div class="px-6 py-4 bg-slate-50 flex justify-end space-x-3 border-t border-slate-100">
                <button id="global-modal-cancel" class="hidden px-4 py-2 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:text-slate-900 rounded-lg text-sm font-medium transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-slate-200 active:scale-[0.98]">Cancel</button>
                <button id="global-modal-confirm" class="px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white rounded-lg text-sm font-medium transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-1 active:scale-[0.98]">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Drag and Drop Library -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="/assets/js/app.js?v=3"></script>
</body>
</html>
