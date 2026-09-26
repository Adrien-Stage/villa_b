<div id="ai-assistant-wrapper" class="fixed bottom-6 right-6 z-[9999] flex flex-col items-end print:hidden no-print">
    
    <!-- Fenêtre de Chat (Masquée par défaut) -->
    <div id="ai-chat-window" class="hidden mb-4 w-80 sm:w-96 bg-white rounded-2xl shadow-2xl border border-secondary/20 overflow-hidden flex-col origin-bottom-right transition-all duration-300 transform scale-95 opacity-0">
        <!-- Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
                    <i data-lucide="bot" class="w-4 h-4 text-white"></i>
                </div>
                <div>
                    <h3 class="text-white text-sm font-semibold">Kuété (Assistant IA)</h3>
                    <p class="text-white/70 text-[10px]">Toujours là pour vous aider</p>
                </div>
            </div>
            <button onclick="toggleAiChat()" class="text-white/70 hover:text-white transition-colors">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <!-- Zone des messages -->
        <div id="ai-chat-messages" class="flex-1 p-4 h-80 overflow-y-auto bg-gray-50 flex flex-col gap-4">
            
            <!-- Message de bienvenue -->
            <div class="flex items-start gap-2 max-w-[85%]">
                <div class="w-6 h-6 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0 mt-1">
                    <i data-lucide="sparkles" class="w-3 h-3 text-indigo-600"></i>
                </div>
                <div class="bg-white border border-gray-100 p-3 rounded-2xl rounded-tl-sm shadow-sm text-xs text-gray-700 leading-relaxed">
                    Bonjour {{ Auth::user()->name ?? '' }} ! 👋 <br>
                    Je suis <strong>Kuété</strong>, ton assistant virtuel. Que puis-je faire pour toi aujourd'hui ?
                </div>
            </div>

            <!-- Suggestions de questions dynamiques par rôle -->
            <div id="ai-chat-suggestions" class="flex flex-wrap gap-2 mt-1">
                @role('manager', 'reception')
                    <button onclick="sendAiMessage('Combien d\'arrivées sont prévues aujourd\'hui ?')" class="px-3 py-1.5 bg-white border border-indigo-100 rounded-full text-[10px] text-indigo-600 hover:bg-indigo-50 transition-colors">
                        Arrivées du jour ?
                    </button>
                    <button onclick="sendAiMessage('Combien de chambres sont disponibles actuellement ?')" class="px-3 py-1.5 bg-white border border-indigo-100 rounded-full text-[10px] text-indigo-600 hover:bg-indigo-50 transition-colors">
                        Chambres dispo ?
                    </button>
                @endrole

                @role('housekeeping_leader', 'housekeeping', 'housekeeping_staff')
                    <button onclick="sendAiMessage('Combien de chambres sont sales en ce moment ?')" class="px-3 py-1.5 bg-white border border-indigo-100 rounded-full text-[10px] text-indigo-600 hover:bg-indigo-50 transition-colors">
                        Chambres à nettoyer ?
                    </button>
                    <button onclick="sendAiMessage('Y a-t-il des chambres en maintenance ?')" class="px-3 py-1.5 bg-white border border-indigo-100 rounded-full text-[10px] text-indigo-600 hover:bg-indigo-50 transition-colors">
                        Chambres en maintenance ?
                    </button>
                @endrole

                @role('restaurant_chief', 'restaurant_staff', 'cashier')
                    <button onclick="sendAiMessage('Combien de commandes restaurant sont en cours ?')" class="px-3 py-1.5 bg-white border border-indigo-100 rounded-full text-[10px] text-indigo-600 hover:bg-indigo-50 transition-colors">
                        Commandes en cours ?
                    </button>
                @endrole

                @role('shop_manager', 'shop_cashier')
                    <button onclick="sendAiMessage('Comment clore ma caisse ?')" class="px-3 py-1.5 bg-white border border-indigo-100 rounded-full text-[10px] text-indigo-600 hover:bg-indigo-50 transition-colors">
                        Comment clore ma caisse ?
                    </button>
                @endrole
            </div>

            <!-- Indicateur de frappe (Masqué par défaut) -->
            <div id="ai-typing-indicator" class="hidden flex items-start gap-2 max-w-[85%]">
                <div class="w-6 h-6 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0 mt-1">
                    <i data-lucide="sparkles" class="w-3 h-3 text-indigo-600"></i>
                </div>
                <div class="bg-white border border-gray-100 p-3 rounded-2xl rounded-tl-sm shadow-sm text-xs text-gray-500 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce"></span>
                    <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></span>
                    <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
                </div>
            </div>

        </div>

        <!-- Input Area -->
        <div class="p-3 bg-white border-t border-gray-100">
            <div class="relative flex items-center">
                <input type="text" id="ai-chat-input" onkeypress="handleAiKeyPress(event)" placeholder="Posez votre question..." class="w-full bg-gray-50 border border-gray-200 text-sm rounded-full pl-4 pr-10 py-2.5 focus:outline-none focus:border-indigo-300 focus:ring-1 focus:ring-indigo-300 transition-all">
                <button type="button" onclick="submitAiMessage()" class="absolute right-1 w-8 h-8 flex items-center justify-center bg-indigo-600 text-white rounded-full hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <i data-lucide="send" class="w-3.5 h-3.5 ml-0.5"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Bouton Flottant -->
    <button onclick="toggleAiChat()" id="ai-chat-button" class="group relative flex items-center justify-center w-14 h-14 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full shadow-lg shadow-indigo-500/30 hover:shadow-indigo-500/50 hover:scale-105 transition-all duration-300">
        <!-- Glow effect -->
        <div class="absolute inset-0 rounded-full bg-white opacity-0 group-hover:opacity-20 transition-opacity"></div>
        <i data-lucide="sparkles" class="w-6 h-6 text-white absolute"></i>
        
        <!-- Tooltip -->
        <span class="absolute right-full mr-4 bg-gray-900 text-white text-[10px] font-semibold px-2 py-1 rounded-md opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity whitespace-nowrap">
            Besoin d'aide ?
        </span>
    </button>

</div>

<script>
    let aiConversationHistory = [];

    document.addEventListener('DOMContentLoaded', () => {
        const chatWindow = document.getElementById('ai-chat-window');
        const isOpen = localStorage.getItem('ai_chat_open') === 'true';
        
        if (isOpen) {
            chatWindow.classList.remove('hidden');
            chatWindow.classList.remove('scale-95', 'opacity-0');
            chatWindow.classList.add('scale-100', 'opacity-100');
        }

        // Restaurer l'historique de la conversation
        const storedHistory = sessionStorage.getItem('aiConversationHistory');
        if (storedHistory) {
            try {
                const history = JSON.parse(storedHistory);
                if (history && history.length > 0) {
                    aiConversationHistory = history;
                    // Cacher les suggestions si l'historique n'est pas vide
                    const suggestions = document.getElementById('ai-chat-suggestions');
                    if (suggestions) suggestions.style.display = 'none';

                    // Réafficher les messages
                    history.forEach(msg => {
                        appendMessageToUI(msg.role, msg.content, true);
                    });
                }
            } catch (e) {
                console.error("Erreur lors de la restauration de l'historique du chat IA", e);
            }
        }
    });

    function toggleAiChat() {
        const chatWindow = document.getElementById('ai-chat-window');
        
        if (chatWindow.classList.contains('hidden')) {
            chatWindow.classList.remove('hidden');
            localStorage.setItem('ai_chat_open', 'true');
            requestAnimationFrame(() => {
                chatWindow.classList.remove('scale-95', 'opacity-0');
                chatWindow.classList.add('scale-100', 'opacity-100');
            });
            setTimeout(() => { document.getElementById('ai-chat-input').focus(); }, 300);
        } else {
            localStorage.setItem('ai_chat_open', 'false');
            chatWindow.classList.remove('scale-100', 'opacity-100');
            chatWindow.classList.add('scale-95', 'opacity-0');
            setTimeout(() => { chatWindow.classList.add('hidden'); }, 300);
        }
    }

    function handleAiKeyPress(event) {
        if (event.key === 'Enter') {
            submitAiMessage();
        }
    }

    function submitAiMessage() {
        const input = document.getElementById('ai-chat-input');
        const text = input.value.trim();
        if (text) {
            sendAiMessage(text);
            input.value = '';
        }
    }

    async function sendAiMessage(text) {
        // Cacher les suggestions
        const suggestions = document.getElementById('ai-chat-suggestions');
        if (suggestions) suggestions.style.display = 'none';

        appendMessageToUI('user', text);
        aiConversationHistory.push({ role: 'user', content: text });
        sessionStorage.setItem('aiConversationHistory', JSON.stringify(aiConversationHistory));

        const typingIndicator = document.getElementById('ai-typing-indicator');
        const messagesContainer = document.getElementById('ai-chat-messages');
        
        // Afficher l'indicateur de frappe
        messagesContainer.appendChild(typingIndicator); // Le déplace à la fin
        typingIndicator.classList.remove('hidden');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        try {
            const response = await fetch('{{ route('ai.chat') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ messages: aiConversationHistory })
            });

            typingIndicator.classList.add('hidden');

            const data = await response.json();

            if (response.ok && data.reply) {
                appendMessageToUI('assistant', data.reply);
                aiConversationHistory.push({ role: 'assistant', content: data.reply });
                sessionStorage.setItem('aiConversationHistory', JSON.stringify(aiConversationHistory));
            } else {
                appendMessageToUI('assistant', "Désolé, une erreur est survenue : " + (data.error || "Erreur inconnue."));
            }
        } catch (error) {
            typingIndicator.classList.add('hidden');
            appendMessageToUI('assistant', "Impossible de me connecter au serveur.");
        }
    }

    function appendMessageToUI(role, text, isRestoring = false) {
        const messagesContainer = document.getElementById('ai-chat-messages');
        const typingIndicator = document.getElementById('ai-typing-indicator');
        const div = document.createElement('div');
        
        // Formatage markdown très basique
        const formattedText = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');

        if (role === 'user') {
            div.className = 'flex items-start gap-2 max-w-[85%] self-end flex-row-reverse';
            div.innerHTML = `
                <div class="bg-indigo-600 border border-indigo-700 p-3 rounded-2xl rounded-tr-sm shadow-sm text-xs text-white leading-relaxed">
                    ${formattedText}
                </div>
            `;
        } else {
            div.className = 'flex items-start gap-2 max-w-[85%]';
            div.innerHTML = `
                <div class="w-6 h-6 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0 mt-1">
                    <i data-lucide="sparkles" class="w-3 h-3 text-indigo-600"></i>
                </div>
                <div class="bg-white border border-gray-100 p-3 rounded-2xl rounded-tl-sm shadow-sm text-xs text-gray-700 leading-relaxed">
                    ${formattedText}
                </div>
            `;
        }

        messagesContainer.insertBefore(div, typingIndicator);
        
        // Re-render lucide icons if any
        if (window.lucide) {
            lucide.createIcons({
                root: div
            });
        }

        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
</script>
