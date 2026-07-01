import React, { useState, useEffect, useReducer } from 'react';

// === CONSTANTS & MOCKS ===
const BUILDINGS = ['Sherdon TJM', 'Yangi Zamon', 'Mega Tower'];
const UNITS = ['kub', 'mt', 'dona', 'kv.m'];
const BLOCKS = ['1', '2', '3', '4', '5'];
const FLOORS = ['-2', '-1', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];

// === REDUCER ===
const initialState = {
  rows: [],
  editingId: null,
};

function init(initialData) {
  const localData = localStorage.getItem('qurilish_ish_haqi');
  if (localData) {
    try {
      return { ...initialData, rows: JSON.parse(localData) };
    } catch (e) {
      console.error(e);
    }
  }
  return initialData;
}

function reducer(state, action) {
  let newState = { ...state };
  switch (action.type) {
    case 'ADD_ROW': {
      const newRow = {
        id: Date.now().toString(),
        name: '',
        building: '',
        block: '',
        floor: '',
        unit: '',
        measure1: 0,
        measure2: 0,
        totalMeasure: 0,
        quantity: 1,
        total: 0,
        price: 0,
        calculatedSum: 0,
        paymentDate: '',
        paymentAmount: 0,
        balance: 0,
      };
      newState = { ...state, rows: [...state.rows, newRow], editingId: newRow.id };
      break;
    }
    case 'UPDATE_ROW': {
      const { id, field, value } = action.payload;
      const updatedRows = state.rows.map((row) => {
        if (row.id === id) {
          const newRow = { ...row, [field]: value };
          
          // Auto-calculations
          newRow.measure1 = parseFloat(newRow.measure1) || 0;
          newRow.measure2 = parseFloat(newRow.measure2) || 0;
          newRow.quantity = parseFloat(newRow.quantity) || 0;
          newRow.price = parseFloat(newRow.price) || 0;
          newRow.paymentAmount = parseFloat(newRow.paymentAmount) || 0;

          newRow.totalMeasure = newRow.measure1 + newRow.measure2;
          newRow.total = newRow.totalMeasure * newRow.quantity;
          newRow.calculatedSum = newRow.total * newRow.price;
          newRow.balance = newRow.calculatedSum - newRow.paymentAmount;
          
          return newRow;
        }
        return row;
      });
      newState = { ...state, rows: updatedRows };
      break;
    }
    case 'DELETE_ROW': {
      newState = {
        ...state,
        rows: state.rows.filter((r) => r.id !== action.payload.id),
      };
      break;
    }
    case 'SET_EDITING': {
      newState = { ...state, editingId: action.payload.id };
      break;
    }
    case 'REORDER_ROWS': {
      const { dragIndex, hoverIndex } = action.payload;
      const dragRow = state.rows[dragIndex];
      const newRows = [...state.rows];
      newRows.splice(dragIndex, 1);
      newRows.splice(hoverIndex, 0, dragRow);
      newState = { ...state, rows: newRows };
      break;
    }
    default:
      return state;
  }

  // Save to localStorage
  localStorage.setItem('qurilish_ish_haqi', JSON.stringify(newState.rows));
  return newState;
}

export default function QurilishIshHaqi() {
  const [state, dispatch] = useReducer(reducer, initialState, init);
  
  // Drag and Drop state
  const [draggedIdx, setDraggedIdx] = useState(null);

  // Statistics calculations
  const stats = state.rows.reduce(
    (acc, row) => {
      acc.totalServices += 1;
      acc.totalCalculatedSum += row.calculatedSum || 0;
      acc.totalPaid += row.paymentAmount || 0;
      acc.totalBalance += row.balance || 0;
      return acc;
    },
    { totalServices: 0, totalCalculatedSum: 0, totalPaid: 0, totalBalance: 0 }
  );

  const formatMoney = (val) => {
    return new Intl.NumberFormat('uz-UZ').format(val || 0) + " so'm";
  };

  const handleDragStart = (e, index) => {
    setDraggedIdx(index);
    e.dataTransfer.effectAllowed = 'move';
  };

  const handleDragOver = (e, index) => {
    e.preventDefault();
    if (draggedIdx === null || draggedIdx === index) return;
    
    dispatch({
      type: 'REORDER_ROWS',
      payload: { dragIndex: draggedIdx, hoverIndex: index },
    });
    setDraggedIdx(index);
  };

  const handleDragEnd = () => {
    setDraggedIdx(null);
  };

  const isEditing = (id) => state.editingId === id;

  const handleChange = (id, field, value) => {
    dispatch({
      type: 'UPDATE_ROW',
      payload: { id, field, value }
    });
  };

  return (
    <div className="p-6 bg-gray-50 min-h-screen">
      <div className="max-w-screen-2xl mx-auto">
        <h1 className="text-2xl font-bold text-gray-800 mb-6">QURILISH ISH HAQI</h1>

        {/* Statistics Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div className="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col">
            <span className="text-sm text-gray-500 font-medium">Umumiy xizmatlar soni</span>
            <span className="text-2xl font-bold text-gray-800 mt-1">{stats.totalServices} ta</span>
          </div>
          <div className="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col">
            <span className="text-sm text-gray-500 font-medium">Jami hisoblangan summa</span>
            <span className="text-2xl font-bold text-blue-600 mt-1">{formatMoney(stats.totalCalculatedSum)}</span>
          </div>
          <div className="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col">
            <span className="text-sm text-gray-500 font-medium">Jami to'langan summa</span>
            <span className="text-2xl font-bold text-green-600 mt-1">{formatMoney(stats.totalPaid)}</span>
          </div>
          <div className="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col">
            <span className="text-sm text-gray-500 font-medium">Qoldiq summa</span>
            <span className="text-2xl font-bold text-red-600 mt-1">{formatMoney(stats.totalBalance)}</span>
          </div>
        </div>

        {/* Action Bar */}
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-lg font-semibold text-gray-700">Xizmatlar ro'yxati</h2>
          <button
            onClick={() => dispatch({ type: 'ADD_ROW' })}
            className="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium flex items-center shadow-sm"
          >
            <svg className="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Yangi Xizmat
          </button>
        </div>

        {/* Table Container */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
          <table className="w-full text-sm text-left text-gray-500">
            <thead className="text-xs text-gray-700 uppercase bg-gray-50">
              <tr>
                <th className="px-3 py-3 w-10"></th>
                <th className="px-3 py-3">№</th>
                <th className="px-3 py-3 min-w-[200px]">Xizmat nomi</th>
                <th className="px-3 py-3 min-w-[150px]">Qurilish obekti</th>
                <th className="px-3 py-3">Blok</th>
                <th className="px-3 py-3">Qavat</th>
                <th className="px-3 py-3">O'lchov</th>
                <th className="px-3 py-3 w-20">O'lchami 1</th>
                <th className="px-3 py-3 w-20">O'lchami 2</th>
                <th className="px-3 py-3 w-24">Jami o'lchami</th>
                <th className="px-3 py-3 w-20">Dona</th>
                <th className="px-3 py-3 w-24">Jami</th>
                <th className="px-3 py-3 min-w-[120px]">Narxi (UZS)</th>
                <th className="px-3 py-3 min-w-[150px]">Hisoblangan</th>
                <th className="px-3 py-3 min-w-[130px]">To'lov sanasi</th>
                <th className="px-3 py-3 min-w-[150px]">To'lov so'mmasi</th>
                <th className="px-3 py-3 min-w-[150px]">Qoldiq</th>
                <th className="px-3 py-3 min-w-[100px] text-center">Amallar</th>
              </tr>
            </thead>
            <tbody>
              {state.rows.length === 0 ? (
                <tr>
                  <td colSpan="18" className="px-6 py-10 text-center text-gray-400">
                    Hozircha xizmatlar mavjud emas. Yangi xizmat qo'shish uchun tugmani bosing.
                  </td>
                </tr>
              ) : (
                state.rows.map((row, index) => {
                  const editMode = isEditing(row.id);
                  return (
                    <tr 
                      key={row.id} 
                      className={`border-b hover:bg-gray-50 transition-colors ${draggedIdx === index ? 'opacity-50 bg-gray-100' : ''}`}
                      draggable
                      onDragStart={(e) => handleDragStart(e, index)}
                      onDragOver={(e) => handleDragOver(e, index)}
                      onDragEnd={handleDragEnd}
                    >
                      <td className="px-3 py-3 text-gray-400 cursor-move">
                        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0-.001-4.001A2 2 0 0 0 13 6zm0 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"></path></svg>
                      </td>
                      <td className="px-3 py-3 font-medium text-gray-900">{index + 1}</td>
                      
                      <td className="px-3 py-3">
                        {editMode ? (
                          <input type="text" value={row.name} onChange={(e) => handleChange(row.id, 'name', e.target.value)} className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border" placeholder="Masalan: Beton" />
                        ) : (
                          <span className={!row.name ? 'text-red-400 text-xs' : 'text-gray-700 font-medium'}>{row.name || 'Kiritilmagan'}</span>
                        )}
                      </td>

                      <td className="px-3 py-3">
                        {editMode ? (
                          <select value={row.building} onChange={(e) => handleChange(row.id, 'building', e.target.value)} className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border">
                            <option value="">Tanlang</option>
                            {BUILDINGS.map(b => <option key={b} value={b}>{b}</option>)}
                          </select>
                        ) : (
                          <span className={!row.building ? 'text-red-400 text-xs' : ''}>{row.building || 'Tanlanmagan'}</span>
                        )}
                      </td>

                      <td className="px-3 py-3">
                        {editMode ? (
                          <select value={row.block} onChange={(e) => handleChange(row.id, 'block', e.target.value)} className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border">
                            <option value="">-</option>
                            {BLOCKS.map(b => <option key={b} value={b}>{b}</option>)}
                          </select>
                        ) : (row.block || '-')}
                      </td>

                      <td className="px-3 py-3">
                        {editMode ? (
                          <select value={row.floor} onChange={(e) => handleChange(row.id, 'floor', e.target.value)} className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border">
                            <option value="">-</option>
                            {FLOORS.map(b => <option key={b} value={b}>{b}</option>)}
                          </select>
                        ) : (row.floor || '-')}
                      </td>

                      <td className="px-3 py-3">
                        {editMode ? (
                          <select value={row.unit} onChange={(e) => handleChange(row.id, 'unit', e.target.value)} className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border">
                            <option value="">-</option>
                            {UNITS.map(b => <option key={b} value={b}>{b}</option>)}
                          </select>
                        ) : (
                          <span className={!row.unit ? 'text-red-400 text-xs' : ''}>{row.unit || 'Tanlanmagan'}</span>
                        )}
                      </td>

                      <td className="px-3 py-3">
                        {editMode ? (
                          <input type="number" min="0" value={row.measure1} onChange={(e) => handleChange(row.id, 'measure1', e.target.value)} className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border text-right" />
                        ) : (row.measure1)}
                      </td>

                      <td className="px-3 py-3">
                        {editMode ? (
                          <input type="number" min="0" value={row.measure2} onChange={(e) => handleChange(row.id, 'measure2', e.target.value)} className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border text-right" />
                        ) : (row.measure2)}
                      </td>

                      <td className="px-3 py-3 font-semibold text-gray-800 bg-gray-50/50">{row.totalMeasure}</td>

                      <td className="px-3 py-3">
                        {editMode ? (
                          <input type="number" min="0" value={row.quantity} onChange={(e) => handleChange(row.id, 'quantity', e.target.value)} className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border text-right" />
                        ) : (row.quantity)}
                      </td>

                      <td className="px-3 py-3 font-bold text-gray-900 bg-gray-50/50">{row.total}</td>

                      <td className="px-3 py-3">
                        {editMode ? (
                          <input type="number" min="0" value={row.price} onChange={(e) => handleChange(row.id, 'price', e.target.value)} className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border text-right" />
                        ) : (
                          <span className="text-gray-900 font-medium">{new Intl.NumberFormat('uz-UZ').format(row.price)}</span>
                        )}
                      </td>

                      <td className="px-3 py-3 font-bold text-blue-600 bg-blue-50/30">
                        {formatMoney(row.calculatedSum)}
                      </td>

                      <td className="px-3 py-3">
                        {editMode ? (
                          <input type="date" value={row.paymentDate} onChange={(e) => handleChange(row.id, 'paymentDate', e.target.value)} className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border" />
                        ) : (row.paymentDate || '-')}
                      </td>

                      <td className="px-3 py-3">
                        {editMode ? (
                          <input type="number" min="0" value={row.paymentAmount} onChange={(e) => handleChange(row.id, 'paymentAmount', e.target.value)} className="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border text-right" />
                        ) : (
                          <span className="text-green-600 font-medium">{new Intl.NumberFormat('uz-UZ').format(row.paymentAmount)}</span>
                        )}
                      </td>

                      <td className="px-3 py-3 font-bold text-red-600 bg-red-50/30">
                        {formatMoney(row.balance)}
                      </td>

                      <td className="px-3 py-3 text-center space-x-2">
                        {editMode ? (
                          <button onClick={() => dispatch({ type: 'SET_EDITING', payload: { id: null } })} className="text-green-600 hover:text-green-800 bg-green-50 p-1.5 rounded-md transition" title="Saqlash">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path></svg>
                          </button>
                        ) : (
                          <button onClick={() => dispatch({ type: 'SET_EDITING', payload: { id: row.id } })} className="text-indigo-600 hover:text-indigo-800 bg-indigo-50 p-1.5 rounded-md transition" title="Tahrirlash">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                          </button>
                        )}
                        <button onClick={() => { if(window.confirm("Rostdan ham o'chirasizmi?")) dispatch({ type: 'DELETE_ROW', payload: { id: row.id } }) }} className="text-red-600 hover:text-red-800 bg-red-50 p-1.5 rounded-md transition" title="O'chirish">
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
