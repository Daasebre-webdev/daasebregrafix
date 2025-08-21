'use client'
import { useState, useRef, useEffect } from 'react'
import { useUser } from '../context/UserContext'


interface Message {
  id: string;
  sender: 'user' | 'ai' | 'error';
  text: string;
  timestamp: Date;
  isEditing?: boolean;
}

interface ChatHistoryItem {
  id: string;
  title: string;
  date: string;
  messages: Message[];
}

export default function PremiumChat() {
  const { user } = useUser()
  const [input, setInput] = useState('')
  const [messages, setMessages] = useState<Message[]>([])
  const [isPaused, setIsPaused] = useState(false)
  const [editContent, setEditContent] = useState('')
  const [showHistory, setShowHistory] = useState(false)
  const [chatHistory, setChatHistory] = useState<ChatHistoryItem[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [isInputActive, setIsInputActive] = useState(false)
  const [isTyping, setIsTyping] = useState(false)
  const [selectedModel, setSelectedModel] = useState('gemini-1.5-flash')
  const [temperature, setTemperature] = useState(0.7)
  const [isMenuOpen, setIsMenuOpen] = useState(false)
  const inputRef = useRef<HTMLTextAreaElement>(null)
  const messagesEndRef = useRef<HTMLDivElement>(null)

  // Gemini Models available
  const availableModels = [
    { id: 'gemini-1.5-flash', name: 'Gemini Flash', description: 'Fastest response time' },
    { id: 'gemini-1.5-pro', name: 'Gemini Pro', description: 'Balanced performance' },
  ]

  // Generate unique IDs
  const generateId = () => Math.random().toString(36).substring(2, 11)

  // Load history from localStorage
  useEffect(() => {
    const savedHistory = localStorage.getItem('chatHistory')
    if (savedHistory) {
      try {
        const parsed = JSON.parse(savedHistory)
        setChatHistory(Array.isArray(parsed) ? parsed : [])
      } catch (error) {
        console.error('Failed to load chat history:', error)
      }
    }
  }, [])

  // Save history to localStorage
  const saveHistory = (newMessages: Message[]) => {
    const newHistoryItem: ChatHistoryItem = {
      id: generateId(),
      title: newMessages[0]?.text.substring(0, 30) || 'New Chat',
      date: new Date().toISOString(),
      messages: newMessages
    }
    
    const updatedHistory = [...chatHistory, newHistoryItem]
    setChatHistory(updatedHistory)
    localStorage.setItem('chatHistory', JSON.stringify(updatedHistory))
  }

  // Message actions
  const startEditing = (id: string) => {
    setMessages(prev => prev.map(msg => 
      msg.id === id ? {...msg, isEditing: true} : msg
    ))
    setEditContent(messages.find(msg => msg.id === id)?.text || '')
  }

  const cancelEdit = (id: string) => {
    setMessages(prev => prev.map(msg => 
      msg.id === id ? {...msg, isEditing: false} : msg
    ))
  }

  const saveEdit = (id: string) => {
    setMessages(prev => prev.map(msg => 
      msg.id === id ? {...msg, text: editContent, isEditing: false} : msg
    ))
  }

  const deleteMessage = (id: string) => {
    setMessages(prev => prev.filter(msg => msg.id !== id))
  }

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text)
  }

  const clearHistory = () => {
    if (confirm('Clear all chat history?')) {
      setChatHistory([])
      localStorage.removeItem('chatHistory')
    }
  }

  // Handle form submission
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!input.trim() || isPaused || isLoading) return

    const userMessage: Message = {
      id: generateId(),
      sender: 'user',
      text: input,
      timestamp: new Date()
    }

    setMessages(prev => [...prev, userMessage])
    setInput('')
    setIsLoading(true)
    setIsTyping(true)

    try {
      const response = await fetch('/api/gemini', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${user?.token || ''}`
        },
        body: JSON.stringify({ 
          message: input,
          history: messages.slice(-6).map(m => m.text),
          model: selectedModel,
          temperature: temperature
        })
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.error || `Request failed (${response.status})`)
      }
      
      const data = await response.json()
      const aiMessage: Message = {
        id: generateId(),
        sender: 'ai',
        text: data.text,
        timestamp: new Date()
      }

      setMessages(prev => [...prev, aiMessage])
      saveHistory([...messages, userMessage, aiMessage])

    } catch (error) {
      setMessages(prev => [...prev, {
        id: generateId(),
        sender: 'error',
        text: `Error: ${error instanceof Error ? error.message : 'Request failed'}. Please try again.`,
        timestamp: new Date()
      }])
    } finally {
      setIsLoading(false)
      setIsTyping(false)
    }
  }

  // Auto-scroll and focus handling
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  useEffect(() => {
    if (isInputActive) {
      inputRef.current?.focus()
    }
  }, [isInputActive])

  // Auto-resize textarea
  useEffect(() => {
    if (inputRef.current) {
      inputRef.current.style.height = 'auto'
      inputRef.current.style.height = `${Math.min(inputRef.current.scrollHeight, 128)}px`
    }
  }, [input])

  return (
    <div className="flex flex-col h-screen bg-gradient-to-br from-gray-50 to-gray-100">
      {/* App Header */}
      <header className="bg-white border-b border-gray-200 py-3 px-4 shadow-sm">
        <div className="max-w-4xl mx-auto flex justify-between items-center">
          <button 
            onClick={() => setShowHistory(!showHistory)}
            className="flex items-center space-x-2 text-gray-700 hover:text-indigo-600"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <span className="font-medium">Chat History</span>
          </button>
          
          <div className="text-center">
            <h1 className="text-xl font-bold text-gray-800">Project Pulse</h1>
            <p className="text-xs text-gray-500">Premium AI Assistant</p>
          </div>
          
          <div className="flex items-center space-x-3">
            <div className="relative">
              <button 
                onClick={() => setIsMenuOpen(!isMenuOpen)}
                className="flex items-center space-x-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-full px-3 py-1 transition-colors"
              >
                <span>{availableModels.find(m => m.id === selectedModel)?.name}</span>
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              
              {isMenuOpen && (
                <div className="absolute right-0 mt-2 w-56 bg-white rounded-md shadow-lg z-10 border border-gray-200">
                  <div className="p-2">
                    <div className="px-3 py-1 text-xs text-gray-500 font-medium">AI Model</div>
                    {availableModels.map(model => (
                      <button
                        key={model.id}
                        onClick={() => {
                          setSelectedModel(model.id)
                          setIsMenuOpen(false)
                        }}
                        className={`w-full text-left px-3 py-2 text-sm flex flex-col ${
                          selectedModel === model.id ? 'bg-indigo-50 text-indigo-700' : 'hover:bg-gray-50'
                        }`}
                      >
                        <span className="font-medium">{model.name}</span>
                        <span className="text-xs text-gray-500">{model.description}</span>
                      </button>
                    ))}
                  </div>
                  <div className="border-t border-gray-200 p-3">
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-xs text-gray-500">Creativity: {temperature.toFixed(1)}</span>
                    </div>
                    <input
                      type="range"
                      min="0"
                      max="1"
                      step="0.1"
                      value={temperature}
                      onChange={(e) => setTemperature(parseFloat(e.target.value))}
                      className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                    />
                    <div className="flex justify-between text-xs text-gray-500 mt-1">
                      <span>Precise</span>
                      <span>Balanced</span>
                      <span>Creative</span>
                    </div>
                  </div>
                </div>
              )}
            </div>
            
            <button 
              onClick={() => setIsPaused(!isPaused)}
              className={`p-2 rounded-full ${isPaused ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600'} hover:opacity-80`}
              title={isPaused ? 'Resume chat' : 'Pause chat'}
            >
              {isPaused ? (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                </svg>
              ) : (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              )}
            </button>
          </div>
        </div>
      </header>

      {/* Main Content Area */}
      <div className="flex-1 flex overflow-hidden">
        {/* Sidebar History */}
        {showHistory && (
          <div className="w-64 bg-white border-r border-gray-200 overflow-y-auto">
            <div className="p-4 border-b border-gray-200 flex justify-between items-center">
              <h3 className="font-medium text-gray-800">Your Chats</h3>
              <div className="flex space-x-2">
                <button 
                  onClick={() => {
                    setMessages([])
                    setShowHistory(false)
                    setIsInputActive(true)
                  }}
                  className="p-1 text-indigo-600 hover:bg-indigo-50 rounded"
                  title="New Chat"
                >
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                  </svg>
                </button>
                <button 
                  onClick={clearHistory}
                  className="p-1 text-red-500 hover:bg-red-50 rounded"
                  title="Clear All"
                >
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
            <div className="divide-y divide-gray-100">
             {chatHistory.length > 0 ? (
  [...chatHistory].reverse().map((history) => (
    <button
      key={history.id}  // This is correct and should work
      className="w-full text-left p-3 hover:bg-gray-50 text-sm flex flex-col"
      onClick={() => {
        setMessages(history.messages)
        setShowHistory(false)
      }}
    >
      <span className="font-medium text-gray-800 truncate">{history.title}</span>
      <span className="text-xs text-gray-500 mt-1">
        {new Date(history.date).toLocaleString()}
      </span>
    </button>
  ))
) : (
  <div className="p-4 text-center text-sm text-gray-500">
    No chat history yet
  </div>
)}
            </div>
          </div>
        )}

        {/* Chat Area */}
        <div className="flex-1 flex flex-col overflow-hidden">
          {/* Messages Container */}
          <div className="flex-1 overflow-y-auto p-4">
            {messages.length === 0 ? (
              <div className="h-full flex flex-col items-center justify-center">
                <div className="max-w-md text-center p-6 bg-white rounded-xl shadow-sm border border-gray-200">
                  <div className="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg className="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                  </div>
                  <h2 className="text-2xl font-bold text-gray-800 mb-2">Premium AI Assistant</h2>
                  <p className="text-gray-600 mb-6">Ask me anything about your project, tasks, or team updates</p>
                  <div className="grid grid-cols-2 gap-3">
                    <button
                      onClick={() => {
                        setIsInputActive(true)
                        setInput('Can you help me analyze this project timeline?')
                      }}
                      className="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-sm transition-colors"
                    >
                      Project Analysis
                    </button>
                    <button
                      onClick={() => {
                        setIsInputActive(true)
                        setInput('What are the best practices for task prioritization?')
                      }}
                      className="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-sm transition-colors"
                    >
                      Task Prioritization
                    </button>
                    <button
                      onClick={() => {
                        setIsInputActive(true)
                        setInput('Generate a status report for our current sprint')
                      }}
                      className="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-sm transition-colors"
                    >
                      Status Report
                    </button>
                    <button
                      onClick={() => {
                        setIsInputActive(true)
                        setInput('Suggest some team collaboration improvements')
                      }}
                      className="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-sm transition-colors"
                    >
                      Team Collaboration
                    </button>
                  </div>
                </div>
              </div>
            ) : (
              <div className="max-w-3xl mx-auto space-y-4">
                {messages.map((msg) => (
                  <div 
                    key={msg.id} 
                    className={`flex ${msg.sender === 'user' ? 'justify-end' : 'justify-start'}`}
                  >
                    <div 
                      className={`max-w-[85%] rounded-2xl px-4 py-3 relative group ${
                        msg.sender === 'user' 
                          ? 'bg-indigo-600 text-white rounded-br-none' 
                          : msg.sender === 'error'
                            ? 'bg-red-100 text-red-800 rounded-bl-none'
                            : 'bg-white text-gray-800 shadow rounded-bl-none'
                      }`}
                    >
                      {msg.isEditing ? (
                        <div className="space-y-2">
                          <textarea
                            value={editContent}
                            onChange={(e) => setEditContent(e.target.value)}
                            className="w-full p-2 border rounded-lg text-gray-800"
                            rows={3}
                            autoFocus
                          />
                          <div className="flex justify-end space-x-2">
                            <button 
                              onClick={() => saveEdit(msg.id)}
                              className="px-3 py-1 bg-green-600 text-white rounded-lg text-sm"
                            >
                              Save
                            </button>
                            <button 
                              onClick={() => cancelEdit(msg.id)}
                              className="px-3 py-1 bg-gray-500 text-white rounded-lg text-sm"
                            >
                              Cancel
                            </button>
                          </div>
                        </div>
                      ) : (
                        <>
                          <pre className="whitespace-pre-wrap font-sans">{msg.text}</pre>
                          <div className="flex items-center justify-between mt-1">
                            <span className="text-xs opacity-70">
                              {new Date(msg.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                            </span>
                            <div className="flex space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                              {msg.sender === 'user' ? (
                                <>
                                  <button 
                                    onClick={() => startEditing(msg.id)}
                                    className={`p-1 rounded-full ${
                                      msg.sender === 'user' 
                                        ? 'hover:bg-white/20' 
                                        : 'hover:bg-gray-200'
                                    }`}
                                    title="Edit"
                                  >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                  </button>
                                  <button 
                                    onClick={() => deleteMessage(msg.id)}
                                    className={`p-1 rounded-full ${
                                      msg.sender === 'user' 
                                        ? 'hover:bg-white/20' 
                                        : 'hover:bg-gray-200'
                                    }`}
                                    title="Delete"
                                  >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                  </button>
                                </>
                              ) : (
                                <button 
                                  onClick={() => copyToClipboard(msg.text)}
                                  className="p-1 rounded-full hover:bg-gray-200"
                                  title="Copy"
                                >
                                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                  </svg>
                                </button>
                              )}
                            </div>
                          </div>
                        </>
                      )}
                    </div>
                  </div>
                ))}
                {isTyping && (
                  <div className="flex justify-start">
                    <div className="bg-white text-gray-800 shadow rounded-2xl rounded-bl-none px-4 py-3 max-w-[85%]">
                      <div className="flex items-center space-x-2">
                        <div className="flex space-x-1">
                          <div className="w-2 h-2 rounded-full bg-gray-400 animate-bounce" style={{ animationDelay: '0ms' }} />
                          <div className="w-2 h-2 rounded-full bg-gray-400 animate-bounce" style={{ animationDelay: '150ms' }} />
                          <div className="w-2 h-2 rounded-full bg-gray-400 animate-bounce" style={{ animationDelay: '300ms' }} />
                        </div>
                        <span className="text-sm text-gray-500">AI is thinking...</span>
                      </div>
                    </div>
                  </div>
                )}
                <div ref={messagesEndRef} />
              </div>
            )}
          </div>

          {/* Input Area */}
          {(isInputActive || messages.length > 0) && (
            <div className="bg-white border-t border-gray-200 p-4">
              <form 
                onSubmit={handleSubmit}
                className="max-w-3xl mx-auto bg-gray-50 rounded-xl p-1 shadow-inner"
              >
                <div className="relative">
                  <textarea
                    ref={inputRef}
                    value={input}
                    onChange={(e) => setInput(e.target.value)}
                    placeholder={isPaused ? "Chat is paused - click play to resume" : "Type your message..."}
                    className={`w-full min-h-[60px] max-h-32 px-4 py-3 pr-12 bg-white rounded-lg border resize-none focus:outline-none focus:ring-2 ${
                      isPaused ? 'border-red-300 focus:ring-red-200' : 'border-gray-300 focus:ring-indigo-200'
                    }`}
                    disabled={isPaused || isLoading}
                    rows={1}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault()
                        handleSubmit(e)
                      }
                    }}
                  />
                  <div className="absolute right-2 bottom-2 flex space-x-1">
                    <button
                      type="button"
                      onClick={() => setInput('')}
                      className={`w-8 h-8 rounded-full flex items-center justify-center ${
                        input.trim() ? 'bg-gray-300 hover:bg-gray-400' : 'bg-transparent'
                      } text-gray-700 transition-colors`}
                      disabled={!input.trim()}
                    >
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                    <button
                      type="submit"
                      disabled={!input.trim() || isPaused || isLoading}
                      className={`w-8 h-8 rounded-full flex items-center justify-center ${
                        isPaused ? 'bg-red-500' : 
                        isLoading ? 'bg-gray-500' : 
                        input.trim() ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-400'
                      } text-white transition-colors`}
                    >
                      {isLoading ? (
                        <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                      ) : (
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                        </svg>
                      )}
                    </button>
                  </div>
                </div>
                <div className="flex items-center justify-between mt-2 px-1">
                  <span className="text-xs text-gray-500">
                    {selectedModel} · {temperature.toFixed(1)} creativity
                  </span>
                </div>
              </form>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}